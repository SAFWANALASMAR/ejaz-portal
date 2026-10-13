<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * فحص ملف رُفع إلى القرص الخاص قبل ربطه بسجله، حين يُقبل PDF أو صورة: في مجلده المتوقع، وفي حد الحجم،
 * ونوعه من محتواه. فالـ PDF حقيقي (PrivatePdf)، والصورة يُعاد ترميزها (ImageSanitizer، بسقف البكسلات)،
 * وما سواهما يُحذف ويُرفض. يُفحص الملف المحفوظ نفسه ولو فُحص عند الرفع، فقد يُستدعى الإجراء من غير اللوحة.
 */
final class PrivateUpload
{
    /**
     * أنواع الصور المقبولة وامتداد كل منها.
     */
    private const IMAGE_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    // حد حقل الرفع (20 ميجابايت)، يُفحص قبل قراءة الملف كاملاً في الذاكرة
    private const MAX_BYTES = 20 * 1024 * 1024;

    /**
     * @param  string  $field  حقل رسالة الرفض
     * @param  string  $invalid  رسالة رفض ما ليس PDF ولا صورة مقروءة
     * @return array{size: int, extension: string} حجم الملف بعد الفحص، وامتداده من نوعه
     *
     * @throws ValidationException
     */
    public static function check(string $disk, string $directory, string $path, string $field, string $invalid): array
    {
        $storage = Storage::disk($disk);
        $inDirectory = StoragePath::inDirectory($path, $directory);
        $contents = $inDirectory && $storage->exists($path) && $storage->size($path) <= self::MAX_BYTES ? (string) $storage->get($path) : '';
        $type = $contents === '' ? '' : (string) (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents);
        // فحص الـ PDF وإعادة ترميز الصورة يقرآن الملف من جديد، فلا تبقى نسخته هنا في الذاكرة
        unset($contents);

        if ($type === 'application/pdf') {
            return ['size' => PrivatePdf::check($disk, $directory, $path, $field), 'extension' => 'pdf'];
        }

        if (isset(self::IMAGE_TYPES[$type])) {
            try {
                ImageSanitizer::strip($disk, $path);

                return ['size' => (int) $storage->size($path), 'extension' => self::IMAGE_TYPES[$type]];
            } catch (RuntimeException) {
                // يُحذف أدناه
            }
        }

        if ($inDirectory) {
            $storage->delete($path);
        }

        throw ValidationException::withMessages([$field => $invalid]);
    }
}
