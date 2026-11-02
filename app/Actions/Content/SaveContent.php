<?php

namespace App\Actions\Content;

use App\Models\Album;
use App\Models\Contracts\HasPublicImages;
use App\Models\Page;
use App\Models\User;
use App\Support\EditablePages;
use App\Support\PublicImages;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * حفظ سجل محتوى من اللوحة (صفحة، أو مبادرة، أو عضو مجلس): الصلاحية، ثم تنظيف صوره الجديدة
 * قبل نشرها، ثم الحفظ، ثم حذف ما خرج منه من صور. والسجل يُسجَّل ويمسح ذاكرة المحتوى بنفسه
 * (LogsActivity وFlushesContentCache).
 */
class SaveContent
{
    /**
     * @template TModel of Model&HasPublicImages
     *
     * @param  TModel  $record  سجل جديد (لم يُحفظ) أو موجود
     * @param  array<string, mixed>  $data
     * @return TModel
     *
     * @throws AuthorizationException
     * @throws ValidationException صورة لا تُقرأ، أو تغيير معرّف «عن الجمعية»
     */
    public function handle(User $actor, Model&HasPublicImages $record, array $data): Model
    {
        $allowed = $record->exists ? $actor->can('update', $record) : $actor->can('create', $record::class);

        if (! $allowed) {
            throw new AuthorizationException;
        }

        // معرّفا «عن الجمعية» و«سياسة الخصوصية» ثابتان، لأن رابطيهما مبنيان عليهما
        if ($record instanceof Page && $record->exists && in_array($record->slug, Page::LOCKED_SLUGS, true) && ($data['slug'] ?? $record->slug) !== $record->slug) {
            throw ValidationException::withMessages(['slug' => __('content.page_slug_locked')]);
        }

        // ومعرّفات صفحات الموقع الثابتة محجوزة لـ«محرر الصفحات» (EditablePages): لا ينشئ صفحة بها ولا ينقل إليها صفحة أخرى إلا
        // من له pages.edit_all. وتعديل الصفحة الثابتة نفسها بها وحدها (PagePolicy)، كأمر استيراد الموقع السابق لمسودة «عن الجمعية»
        $slug = $record instanceof Page ? ($data['slug'] ?? $record->slug) : null;

        if ($record instanceof Page && in_array($slug, EditablePages::slugs(), true) && (! $record->exists || $record->getOriginal('slug') !== $slug) && ! $actor->can('pages.edit_all')) {
            throw ValidationException::withMessages(['slug' => __('page_editor.slug_reserved')]);
        }

        $before = $record->publicImages();
        $record->fill($data);
        $after = $record->publicImages();

        // ألبوم منشور بلا صور لا معنى له: شرط النموذج يُعاد هنا لكل مستدعٍ
        if ($record instanceof Album && $record->is_published && $record->photoList() === []) {
            throw ValidationException::withMessages(['photos' => __('albums.validation.needs_photos')]);
        }

        // الجديد في آخر الترتيب
        if (! $record->exists && in_array('sort', $record->getFillable(), true) && ! array_key_exists('sort', $data)) {
            $record->setAttribute('sort', (int) $record->newQuery()->max('sort') + 1);
        }

        // الصورة الجديدة لا تكون لسجل آخر، فلا يعيد الحفظ ترميزها، ولا يحذفها لاحقاً حذفُ هذا
        // السجل أو تعديله. وجداول المحتوى صغيرة (عشرات السجلات)، فيُقرأ ما فيها من صور مرة
        $used = self::imagesOfOtherRecords($record);

        foreach ($after as $field => $paths) {
            if (array_intersect(array_diff($paths, $before[$field] ?? []), $used) !== []) {
                throw ValidationException::withMessages([$field => __('validation.image_in_use')]);
            }

            PublicImages::prepare($before[$field] ?? [], $paths, $record->imageDirectory(), $field);
        }

        DB::transaction(fn () => $record->save());

        PublicImages::discard(self::flatten($before), self::flatten($after), $record->imageDirectory());

        return $record;
    }

    /**
     * @return list<string>
     */
    private static function imagesOfOtherRecords(Model&HasPublicImages $record): array
    {
        // أعمدة الصور وحدها: مفاتيح publicImages أسماء أعمدتها
        $columns = [$record->getKeyName(), ...array_keys($record->publicImages())];

        return $record->newQuery()
            ->when($record->exists, fn ($query) => $query->whereKeyNot($record->getKey()))
            ->get($columns)
            ->flatMap(fn (Model&HasPublicImages $other): array => self::flatten($other->publicImages()))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, list<string>>  $images
     * @return list<string>
     */
    private static function flatten(array $images): array
    {
        return array_merge(...array_values($images));
    }
}
