<?php

namespace App\Support;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * تقديم ملف من القرص الخاص بعد فحص الصلاحية عند المستدعي: على القرص المحلي من التطبيق (بطلب الجزء
 * Range للمقاطع)، وعلى التخزين السحابي برابط مؤقت موقّع قصير، فلا تمر المقاطع الثقيلة عبر PHP لآلاف
 * المتقدمين. والرابط الموقّع يلزمه نطاق التخزين في CSP_MEDIA_SRC (docs/decisions.md).
 *
 * وملف PDF من غير الإداريين لا يُعرض في المتصفح ($download): يُنزَّل كمرفقات المتقدمين (قرار
 * 2026-10-07)، وعلى استجابته سياسة CSP الكاملة من SecurityHeaders.
 *
 * ورأس Content-Disposition يُبنى بـ HeaderUtils::makeDisposition: الاسم بلا محارف تحكم ولا فواصل مسار،
 * ويصل كاملاً في filename* (UTF-8)، ومعه اسم احتياطي ASCII بلا اقتباس.
 */
final class PrivateFileResponse
{
    public static function make(string $disk, string $path, string $mime, string $name, int $minutes, bool $download = false): Response
    {
        $disposition = self::disposition($download ? HeaderUtils::DISPOSITION_ATTACHMENT : HeaderUtils::DISPOSITION_INLINE, $name);
        $headers = ['Content-Type' => $mime, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff', 'Content-Disposition' => $disposition];
        $storage = Storage::disk($disk);

        abort_unless($storage->exists($path), 404);

        if (self::isLocal($disk)) {
            return response()->file($storage->path($path), $headers);
        }

        return self::temporaryRedirect($disk, $path, $mime, $name, $minutes, $download);
    }

    public static function isLocal(string $disk): bool
    {
        return config('filesystems.disks.'.$disk.'.driver') === 'local';
    }

    /**
     * تحويل 302 إلى رابط التخزين الموقّع. والتحويل نفسه لا يُخزَّن عند وسيط مشترك، فلا يصل رابط إلى غير من طلبه.
     *
     * شرط مسبق على المستدعي: فحص الصلاحية (أو النشر) ووجود الملف قبله، فالدالة لا تفحص شيئاً، ورابط ملف غائب يرد
     * من التخزين 404 لا من البوابة. كما في make() أعلاه، وGovernanceController::publishedDocument،
     * وLibraryController::publishedItem. ولا يُكرَّر فحص الوجود هنا، فهو طلب ثانٍ إلى التخزين لكل ملف.
     */
    public static function temporaryRedirect(string $disk, string $path, string $mime, string $name, int $minutes, bool $download = false): RedirectResponse
    {
        $disposition = self::disposition($download ? HeaderUtils::DISPOSITION_ATTACHMENT : HeaderUtils::DISPOSITION_INLINE, $name);

        return redirect()->away(Storage::disk($disk)->temporaryUrl($path, now()->addMinutes($minutes), [
            'ResponseContentType' => $mime,
            'ResponseContentDisposition' => $disposition,
            'ResponseCacheControl' => 'private, no-store',
        ]))->header('Cache-Control', 'private, no-store');
    }

    private static function disposition(string $type, string $name): string
    {
        $name = str_replace(['/', '\\'], '_', (string) preg_replace('/[\x00-\x1F\x7F]/u', '', $name));
        $fallback = (string) preg_replace('/[^A-Za-z0-9._ -]/', '_', Str::ascii($name));

        return HeaderUtils::makeDisposition($type, $name, trim($fallback, '._ ') === '' ? 'file' : $fallback);
    }
}
