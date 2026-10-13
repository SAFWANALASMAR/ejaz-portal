<?php

namespace App\Support;

/**
 * حارس مسارات الملفات المحفوظة: المسار داخل مجلده المتوقع، بلا صعود (..) ولا محرف null. فلا يحفظ إجراء
 * ولا يحذف ولا يقدّم ملفاً خارج مجلده، ولو جاء المسار من طلب معدّل.
 */
final class StoragePath
{
    public static function inDirectory(string $path, string $directory): bool
    {
        return str_starts_with($path, $directory.'/') && ! str_contains($path, '..') && ! str_contains($path, "\0");
    }
}
