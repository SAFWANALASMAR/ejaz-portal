<?php

namespace App\Actions\Evidence;

use App\Enums\ActivityEvent;
use App\Enums\ArchiveItemType;
use App\Models\Album;
use App\Models\ArchiveItem;
use App\Models\EvidenceGallery;
use App\Models\EvidenceGalleryItem;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * حفظ معرض شواهد ببنوده مرتبة: كل بند ألبوم من مكتبة الصور أو شاهد من الأرشيف، ومعه ما اختير منه (وبلا اختيار
 * كله). لا يُنسخ ملف: البند إشارة إلى السجل.
 * - الشاهد من تصنيف يطّلع عليه الحافظ وحده (ArchiveItemPolicy)، ومن نوع «شاهد» لا المراسلات والوثائق.
 * - والألبوم منشور وحده: صوره على القرص العام برابط دائم لا يسري عليه إلغاء رابط المعرض، فلا يُكشف به ما لم يُنشر
 *   (مراجعة المشرف على #103، م١). ويُفحص النشر عند العرض أيضاً (EvidenceGalleryItem::visibleTo).
 * - والبند الباقي من مصدره نفسه يبقى صفه، فلا تنكسر روابط ملفاته في صفحة مفتوحة بحفظ لم يغيّره.
 * - وتغيّر البنود يُسجَّل بما قبله وما بعده (من يجعل ملفاً خاصاً في متناول رابط بلا حساب).
 */
class SaveEvidenceGallery
{
    /**
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, EvidenceGallery $gallery, array $data): EvidenceGallery
    {
        $allowed = $gallery->exists ? $actor->can('update', $gallery) : $actor->can('create', EvidenceGallery::class);

        if (! $allowed) {
            throw new AuthorizationException;
        }

        $validated = Validator::make($data, [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'program_id' => ['nullable', 'integer', Rule::exists('programs', 'id')],
            'period_from' => ['nullable', 'date'],
            'period_to' => ['nullable', 'date', 'after_or_equal:period_from'],
            'items' => ['array', 'max:'.EvidenceGallery::MAX_ITEMS],
            'items.*.source' => ['required', Rule::in(['album', 'archive'])],
            'items.*.album_id' => ['nullable', 'required_if:items.*.source,album', 'integer'],
            'items.*.archive_item_id' => ['nullable', 'required_if:items.*.source,archive', 'integer'],
            'items.*.paths' => ['nullable', 'array', 'max:'.Album::MAX_PHOTOS],
            'items.*.paths.*' => ['string', 'max:255'],
        ], attributes: [
            'title' => __('evidence.fields.title'),
            'period_from' => __('evidence.fields.period_from'),
            'period_to' => __('evidence.fields.period_to'),
        ])->validate();

        $items = [];

        foreach (array_values($validated['items'] ?? []) as $position => $item) {
            $items[] = ['position' => $position, ...self::item($actor, $item, $position)];
        }

        DB::transaction(function () use ($actor, $gallery, $validated, $items): void {
            $gallery->fill([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'program_id' => $validated['program_id'] ?? null,
                'period_from' => $validated['period_from'] ?? null,
                'period_to' => $validated['period_to'] ?? null,
            ]);

            if (! $gallery->exists) {
                $gallery->created_by = $actor->id;
            }

            $gallery->save();

            $before = $gallery->items()->get();
            $after = self::sync($gallery, $before, $items);

            if (self::summary($before) !== self::summary($after)) {
                activity('documents')->causedBy($actor)->performedOn($gallery)
                    ->event(ActivityEvent::EvidenceItemsUpdated->value)
                    ->withProperties(['old' => self::summary($before), 'attributes' => self::summary($after)])
                    ->log(ActivityEvent::EvidenceItemsUpdated->value);
            }
        });

        return $gallery;
    }

    /**
     * البنود الجديدة بترتيبها: ما كان من المصدر نفسه يُحدَّث صفه (فيبقى معرّفه في روابط الملفات)، والباقي يُنشأ،
     * والزائد يُحذف.
     *
     * @param  iterable<EvidenceGalleryItem>  $before
     * @param  list<array{position: int, album_id: ?int, archive_item_id: ?int, paths: ?list<string>}>  $items
     * @return list<EvidenceGalleryItem>
     */
    private static function sync(EvidenceGallery $gallery, iterable $before, array $items): array
    {
        $pool = [];

        foreach ($before as $row) {
            $pool[self::sourceKey($row->album_id, $row->archive_item_id)][] = $row;
        }

        $saved = [];

        foreach ($items as $item) {
            $key = self::sourceKey($item['album_id'], $item['archive_item_id']);
            $row = ($pool[$key] ?? []) === [] ? new EvidenceGalleryItem : array_shift($pool[$key]);
            $row->fill($item);
            $gallery->items()->save($row);
            $saved[] = $row;
        }

        $leftover = collect($pool)->flatten()->map(fn (EvidenceGalleryItem $row): int => $row->id)->all();
        EvidenceGalleryItem::query()->whereKey($leftover)->delete();

        return $saved;
    }

    private static function sourceKey(?int $album, ?int $archive): string
    {
        return $album !== null ? 'album:'.$album : 'archive:'.$archive;
    }

    /**
     * البنود كما تُسجَّل: المصدر وعدد المختار منه (وnull: كله)، بلا مسارات الملفات.
     *
     * @param  iterable<EvidenceGalleryItem>  $items
     * @return list<array{source: string, selected: ?int}>
     */
    private static function summary(iterable $items): array
    {
        $summary = [];

        foreach ($items as $item) {
            $summary[] = ['source' => self::sourceKey($item->album_id, $item->archive_item_id), 'selected' => $item->paths === null ? null : count($item->paths)];
        }

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{album_id: ?int, archive_item_id: ?int, paths: ?list<string>}
     *
     * @throws ValidationException
     */
    private static function item(User $actor, array $item, int $position): array
    {
        $key = 'items.'.$position;

        if ($item['source'] === 'album') {
            $album = Album::query()->find((int) $item['album_id']);

            if ($album === null || ! $album->is_published) {
                throw ValidationException::withMessages([$key.'.album_id' => __('evidence.validation.source')]);
            }

            $available = $album->photoList();
            $ids = ['album_id' => $album->id, 'archive_item_id' => null];
        } else {
            $archive = ArchiveItem::query()->with('category')->find((int) $item['archive_item_id']);

            if ($archive === null || $archive->type !== ArchiveItemType::Evidence || ! $actor->can('view', $archive)) {
                throw ValidationException::withMessages([$key.'.archive_item_id' => __('evidence.validation.source')]);
            }

            $available = array_column($archive->files ?? [], 'path');
            $ids = ['album_id' => null, 'archive_item_id' => $archive->id];
        }

        $paths = array_values(array_unique(array_filter((array) ($item['paths'] ?? []), 'is_string')));

        // المختار من السجل نفسه وحده، فلا يُدخل مسار ملف آخر في القرص
        if (array_diff($paths, $available) !== []) {
            throw ValidationException::withMessages([$key.'.paths' => __('evidence.validation.paths')]);
        }

        return [...$ids, 'paths' => $paths === [] ? null : $paths];
    }
}
