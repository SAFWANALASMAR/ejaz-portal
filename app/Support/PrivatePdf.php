<?php

namespace App\Support;

use App\Rules\RealPdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * فحص ملف PDF رُفع إلى القرص الخاص قبل ربطه بسجله: في مجلده المتوقع، وPDF حقيقي (RealPdf). يُفحص الملف
 * المحفوظ نفسه ولو فُحص عند الرفع، فقد يُستدعى الإجراء من غير اللوحة. والمخالف يُحذف.
 */
final class PrivatePdf
{
    /**
     * @return int حجم الملف بالبايت
     *
     * @throws ValidationException
     */
    public static function check(string $disk, string $directory, string $path, string $field): int
    {
        $storage = Storage::disk($disk);
        $inDirectory = StoragePath::inDirectory($path, $directory);
        $problem = $inDirectory && $storage->exists($path) ? RealPdf::problem((string) $storage->get($path)) : 'validation.real_pdf';

        if ($problem !== null) {
            if ($inDirectory) {
                $storage->delete($path);
            }

            throw ValidationException::withMessages([$field => __($problem)]);
        }

        return (int) $storage->size($path);
    }
}
