<?php

namespace App\Actions\Content;

use App\Models\Contracts\HasPublicImages;
use App\Models\Page;
use App\Models\User;
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

        // معرّف «عن الجمعية» ثابت، لأن رابطها مبني عليه
        if ($record instanceof Page && $record->exists && $record->slug === Page::ABOUT && ($data['slug'] ?? Page::ABOUT) !== Page::ABOUT) {
            throw ValidationException::withMessages(['slug' => __('content.about_slug_locked')]);
        }

        $before = $record->publicImages();
        $record->fill($data);
        $after = $record->publicImages();

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
        return $record->newQuery()
            ->when($record->exists, fn ($query) => $query->whereKeyNot($record->getKey()))
            ->get()
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
