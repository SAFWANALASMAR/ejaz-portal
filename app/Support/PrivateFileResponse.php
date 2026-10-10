<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * تقديم ملف من القرص الخاص بعد فحص الصلاحية عند المستدعي: على القرص المحلي من التطبيق (بطلب الجزء
 * Range للمقاطع)، وعلى التخزين السحابي برابط مؤقت موقّع قصير، فلا تمر المقاطع الثقيلة عبر PHP لآلاف
 * المتقدمين. والرابط الموقّع يلزمه نطاق التخزين في CSP_MEDIA_SRC (docs/decisions.md).
 */
final class PrivateFileResponse
{
    public static function make(string $disk, string $path, string $mime, string $name, int $minutes): Response
    {
        $headers = ['Content-Type' => $mime, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'];
        $storage = Storage::disk($disk);

        abort_unless($storage->exists($path), 404);

        if (config('filesystems.disks.'.$disk.'.driver') === 'local') {
            return response()->file($storage->path($path), $headers + ['Content-Disposition' => 'inline; filename="'.$name.'"']);
        }

        return redirect()->away($storage->temporaryUrl($path, now()->addMinutes($minutes), [
            'ResponseContentType' => $mime,
            'ResponseContentDisposition' => 'inline; filename="'.$name.'"',
            'ResponseCacheControl' => 'private, no-store',
        ]));
    }
}
