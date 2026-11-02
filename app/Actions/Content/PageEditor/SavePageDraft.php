<?php

namespace App\Actions\Content\PageEditor;

use App\Enums\ActivityEvent;
use App\Enums\PageRevisionStatus;
use App\Enums\SiteIcon;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\User;
use App\Rules\PlainText;
use App\Support\ContentBlocks;
use App\Support\EditablePages;
use App\Support\PublicImages;
use App\Support\RichText;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * حفظ مسودة الصفحة الثابتة من «محرر الصفحات»: العنوان، والوصف، وصورة الرأس، والمقاطع بنصوصها وصورها ومفتاح إظهار كل مقطع
 * وترتيبها. لا يمس ما يُعرض للزائر حتى تُنشر (PublishPageDraft). والصور الجديدة تُنظَّف قبل حفظها (PublicImages)، ولا تُحذف
 * المستبدلة لأن نسخة سابقة قد تعود إليها. ويُسجَّل الحفظ بما تغيّر فيه: الحقول، وإعادة الترتيب، وتغيّر الإظهار.
 */
class SavePageDraft
{
    /**
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, Page $page, array $data): PageRevision
    {
        if (! EditablePages::isEditable($page) || ! $actor->can('update', $page)) {
            throw new AuthorizationException;
        }

        // مفاتيح Filament الداخلية للمقاطع (uuid) تسقط هنا، فتُفحص المقاطع بترتيبها
        $data['blocks'] = array_values(is_array($data['blocks'] ?? null) ? $data['blocks'] : []);

        $validated = Validator::make($data, self::rules())->validate();
        $content = EditablePages::normalize($validated);

        $known = EditablePages::knownImages($page);
        $images = (new PageRevision(['content' => $content]))->publicImages();
        PublicImages::prepare($known, $images['blocks'], Page::IMAGE_DIRECTORY, 'blocks');
        PublicImages::prepare($known, $images['image'], PageRevision::heroImageDirectory(), 'image');

        return DB::transaction(function () use ($actor, $page, $content): PageRevision {
            Page::query()->whereKey($page->id)->lockForUpdate()->firstOrFail();

            $draft = PageRevision::query()->where('page_id', $page->id)->status(PageRevisionStatus::Draft)->first();
            $previous = $draft !== null ? $draft->content : PageRevision::query()->where('page_id', $page->id)->latest('id')->first()?->content;
            $previous = is_array($previous) ? EditablePages::normalize($previous) : EditablePages::liveContent($page);

            $draft ??= new PageRevision(['page_id' => $page->id, 'status' => PageRevisionStatus::Draft]);
            $draft->fill(['content' => $content, 'author_id' => $actor->id])->save();

            activity('content')->causedBy($actor)->performedOn($page)
                ->event(ActivityEvent::PageDraftSaved->value)
                ->withProperties(['page' => EditablePages::keyForSlug($page->slug), 'revision' => $draft->id, ...self::changes($previous, $content)])
                ->log(ActivityEvent::PageDraftSaved->value);

            return $draft;
        });
    }

    /**
     * حدود محرر الصفحات القائم (ContentPageForm وContentFields) لكل مستدعٍ، لا للشاشة وحدها.
     *
     * @return array<string, mixed>
     */
    private static function rules(): array
    {
        $text = fn (int $max, bool $multiline = false): array => ['nullable', 'string', 'max:'.$max, new PlainText(multiline: $multiline)];

        return [
            'title' => ['required', 'string', 'max:'.EditablePages::TITLE_MAX, new PlainText],
            'summary' => $text(EditablePages::SUMMARY_MAX, true),
            'image' => ['nullable', 'string', 'max:255'],
            'blocks' => ['array', 'max:'.EditablePages::BLOCKS_MAX],
            'blocks.*.type' => ['required', Rule::in(ContentBlocks::TYPES)],
            'blocks.*.data' => ['required', 'array'],
            'blocks.*.data.visible' => ['sometimes', 'boolean'],
            'blocks.*.data.heading' => $text(160),
            'blocks.*.data.body' => ['required_if:blocks.*.type,text', 'nullable', 'string', 'max:'.RichText::MAX_LENGTH],
            'blocks.*.data.items' => ['required_if:blocks.*.type,cards', 'array', 'max:12'],
            'blocks.*.data.items.*.icon' => ['nullable', Rule::enum(SiteIcon::class)],
            'blocks.*.data.items.*.title' => ['required', 'string', 'max:120', new PlainText],
            'blocks.*.data.items.*.text' => $text(600, true),
            'blocks.*.data.items.*.picture' => ['nullable', 'string', 'max:255'],
            'blocks.*.data.picture' => ['required_if:blocks.*.type,image', 'nullable', 'string', 'max:255'],
            'blocks.*.data.alt' => $text(300),
            'blocks.*.data.caption' => $text(200),
        ];
    }

    /**
     * ما تغيّر بين المحتوى السابق والجديد، لسجل العمليات: أسماء الحقول، وهل أُعيد ترتيب المقاطع نفسها، وهل تغيّر إظهار مقطع.
     *
     * @param  array{title: string, summary: ?string, image: ?string, blocks: list<array{type: string, data: array<string, mixed>}>}  $before
     * @param  array{title: string, summary: ?string, image: ?string, blocks: list<array{type: string, data: array<string, mixed>}>}  $after
     * @return array{fields: list<string>, reordered: bool, visibility_changed: bool, sections: int, hidden: int}
     */
    private static function changes(array $before, array $after): array
    {
        // jsonb يعيد مفاتيح الكائن بترتيبه هو، فالمقارنة بعد ترتيب المفاتيح
        $before = self::canonical($before);
        $after = self::canonical($after);

        $signature = function (array $block): string {
            unset($block['data']['visible']);

            return (string) json_encode($block);
        };
        $sorted = function (array $list): array {
            sort($list);

            return $list;
        };
        $withVisibility = fn (array $blocks): array => $sorted(array_map(fn (array $block): string => $signature($block).'|'.($block['data']['visible'] === false ? 0 : 1), $blocks));

        $was = array_map($signature, $before['blocks']);
        $now = array_map($signature, $after['blocks']);
        $sameSections = $sorted($was) === $sorted($now);

        return [
            'fields' => array_values(array_filter(['title', 'summary', 'image', 'blocks'], fn (string $field): bool => $before[$field] !== $after[$field])),
            'reordered' => $sameSections && $was !== $now,
            'visibility_changed' => $sameSections && $withVisibility($before['blocks']) !== $withVisibility($after['blocks']),
            'sections' => count($after['blocks']),
            'hidden' => count(array_filter($after['blocks'], fn (array $block): bool => $block['data']['visible'] === false)),
        ];
    }

    /**
     * المصفوفة بمفاتيح مرتبة في كل مستوى، والقوائم بترتيبها.
     *
     * @template T
     *
     * @param  T  $value
     * @return T
     */
    private static function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $value = array_map(fn (mixed $item): mixed => self::canonical($item), $value);

        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }
}
