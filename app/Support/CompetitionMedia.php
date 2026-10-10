<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * وسائط الاختبار التي يرفعها المسؤول: الفيديو التعريفي ووسائط الأسئلة. في القرص الخاص بمجلدها، ولا
 * تُقدَّم إلا لمن بلغ مرحلتها (ExamController)، فلا تُكشف الأسئلة قبل الاختبار.
 */
final class CompetitionMedia
{
    public const DIRECTORY = 'competition-media';

    public static function disk(): string
    {
        return (string) config('filesystems.private_disk', 'local');
    }

    public static function inDirectory(string $path): bool
    {
        return PrivatePdf::inDirectory($path, self::DIRECTORY);
    }

    /**
     * النوع الحقيقي من محتوى الملف (أو من التخزين السحابي)، أو null إن لم يوجد.
     */
    public static function mime(string $path): ?string
    {
        $storage = Storage::disk(self::disk());

        if (! $storage->exists($path)) {
            return null;
        }

        $mime = $storage->mimeType($path);

        return is_string($mime) ? $mime : null;
    }
}
