<?php

namespace App\Support;

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * ملف PDF «للقراءة فقط» لقارئ الصفحة (resources/js/pdf-reader.js) وحده: كتب المكتبة (30 أكتوبر 2026) ووثائق الحوكمة «للاطلاع
 * فقط» (ملاحظات المدير التنفيذي، 1 نوفمبر 2026، البند ٢٢)، بحماية واحدة لكليهما:
 * - يُبث من التطبيق على أي قرص، ولا تحويل إلى رابط تخزين موقّع يُفتح وحده في عارض المتصفح بزر تنزيله ويُتداول حتى نهاية مدته.
 * - inline دائماً بلا Content-Disposition: attachment، وبلا تخزين (private, no-store)، ولا يُقرأ أي معامل في الرابط.
 * - فتح المسار نفسه في تبويب أو إطار (Sec-Fetch-Dest) يعود إلى صفحة العنصر، فلا يصل عارض المتصفح.
 * - حد لكل عنوان أضيق من حد المسار (الملف كاملاً عبر PHP، والقارئ يطلبه مرة لكل فتح — المراجعة الأمنية م١).
 * - السياسة الكاملة لا سطر التضمين (SecurityHeaders::READER_ONLY)، فلا يُضمَّن في إطار.
 * وهذا يصعّب الحفظ ولا يمنعه منعاً تاماً: ما يُعرض على الشاشة يمكن تصويره، والملف يصل المتصفح ليُرسم.
 * والمستدعي يفحص النشر ووجود الملف قبله.
 */
final class ReaderFileResponse
{
    /**
     * فتحات القارئ لكل عنوان في الدقيقة، لكل قسم حده (library وgovernance).
     */
    public const PER_MINUTE = 20;

    public static function make(Request $request, string $disk, string $file, string $name, string $limiter, string $pageUrl): Response
    {
        if (in_array($request->headers->get('Sec-Fetch-Dest'), ['document', 'iframe', 'frame', 'embed', 'object'], true)) {
            return redirect()->to($pageUrl);
        }

        // لكل مشترك (IPv4 كاملاً، أو /64 في IPv6)، فلا يتجاوز الحد من يدوّر عناوين شبكته
        $key = $limiter.'-reader:'.NetworkPrefix::subscriber($request->ip());
        abort_if(RateLimiter::tooManyAttempts($key, self::PER_MINUTE), 429);
        RateLimiter::hit($key, 60);

        $request->attributes->set(SecurityHeaders::READER_ONLY, true);

        return Storage::disk($disk)->response($file, $name, [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow',
        ], 'inline');
    }
}
