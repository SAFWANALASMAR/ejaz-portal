<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * صور السجلات المنشورة على القرص العام (إعدادات الموقع، والصفحات، والمبادرات، والأعضاء):
 * الجديدة تُنظَّف قبل نشرها، والتي خرجت من السجل تُحذف بعد حفظه. والمسارات من مجلد السجل
 * وحده، فلا يمس الإجراء ملفاً غيره ولو استُدعي من غير شاشته.
 */
final class PublicImages
{
    /**
     * يُنظّف الصور الجديدة (في $after لا في $before) من البيانات الوصفية (EXIF). وما لا يُقرأ
     * صورةً يُحذف ويُرفض، ولو اجتاز فحص النوع والأبعاد.
     *
     * @param  list<string>  $before
     * @param  list<string>  $after
     *
     * @throws ValidationException
     */
    public static function prepare(array $before, array $after, string $directory, string $field): void
    {
        foreach (array_diff($after, $before) as $path) {
            if (! StoragePath::inDirectory($path, $directory)) {
                throw self::reject($field);
            }

            try {
                ImageSanitizer::strip('public', $path);
            } catch (Throwable) {
                Storage::disk('public')->delete($path);

                throw self::reject($field);
            }
        }
    }

    /**
     * يحذف الصور التي خرجت من السجل، فلا تبقى منشورة على روابطها.
     *
     * @param  list<string>  $before
     * @param  list<string>  $after
     */
    public static function discard(array $before, array $after, string $directory): void
    {
        $removed = array_filter(array_diff($before, $after), fn (string $path): bool => StoragePath::inDirectory($path, $directory));

        if ($removed !== []) {
            Storage::disk('public')->delete(array_values($removed));
        }
    }

    private static function reject(string $field): ValidationException
    {
        return ValidationException::withMessages([$field => __('validation.unreadable_image')]);
    }
}
