<?php

namespace App\Support;

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * ملف PDF «للقراءة فقط» لقارئ الصفحة (resources/js/pdf-reader.js) وحده: كتب المكتبة (30 أكتوبر 2026) ووثائق الحوكمة «للاطلاع
 * فقط» (ملاحظات المدير التنفيذي، 1 نوفمبر 2026، البند ٢٢)، ومنها كتاب دليل البراهين، بحماية واحدة لكلها:
 * - يُبث من التطبيق على أي قرص، ولا تحويل إلى رابط تخزين موقّع يُفتح وحده في عارض المتصفح بزر تنزيله ويُتداول حتى نهاية مدته.
 * - فتح المسار نفسه في تبويب أو إطار (Sec-Fetch-Dest) يعود إلى صفحة العنصر، فلا يصل عارض المتصفح.
 * - من القارئ وحده (decisions/2026-11-10-feat-reader-download-protection.md): ترويسة X-Ejaz-Reader، وطلب fetch من الموقع نفسه
 *   متى أرسل المتصفح Sec-Fetch-*، ورمز لمرة واحدة للجلسة والعنصر (ReaderTicket). وما سوى ذلك 404 كأنه غير موجود، فلا يأخذ
 *   برنامج تحميل الملف بالرابط الملتقط من الصفحة.
 * - والبايتات مخلوطة بمفتاح الرمز (XOR دوري) بثّاً بقطع، بنوع application/octet-stream بلا اسم ملف، فما يُحفظ منها ليس PDF.
 *   والحجم كما هو، والملف على القرص لا يتغير.
 * - بلا تخزين (private, no-store)، وحد لكل عنوان أضيق من حد المسار (الملف كاملاً عبر PHP، والقارئ يطلبه مرة لكل فتح — المراجعة
 *   الأمنية م١)، والسياسة الكاملة لا سطر التضمين.
 * وهذا يمنع برامج التحميل ونسخ الرابط، ولا يمنع تصوير الشاشة ولا من يفك الشيفرة بأدوات المطور: الملف يصل المتصفح ليُرسم.
 * والمستدعي يفحص النشر ووجود الملف قبله.
 */
final class ReaderFileResponse
{
    /**
     * فتحات القارئ للملف الواحد من كل مشترك في الدقيقة، لكل قسم حده (library وgovernance).
     */
    public const PER_MINUTE = 20;

    public const HEADER = 'X-Ejaz-Reader';

    /**
     * قطعة البث: مضاعف لطول المفتاح، فلا تُقرأ في الذاكرة إلا قطعة واحدة.
     */
    private const CHUNK = 65536;

    public static function make(Request $request, string $disk, string $file, string $scope, string $limiter, string $pageUrl, int $perMinute = self::PER_MINUTE): Response
    {
        if (in_array($request->headers->get('Sec-Fetch-Dest'), ['document', 'iframe', 'frame', 'embed', 'object'], true)) {
            return redirect()->to($pageUrl);
        }

        // طلب fetch من القارئ نفسه. والمتصفح الذي لا يرسل Sec-Fetch-* (سفاري قبل 16.4) يمر بالترويسة والرمز
        abort_unless($request->headers->get(self::HEADER) === '1'
            && in_array($request->headers->get('Sec-Fetch-Site'), [null, 'same-origin'], true)
            && in_array($request->headers->get('Sec-Fetch-Dest'), [null, 'empty'], true), 404);

        // لكل ملف ومشترك (IPv4 كاملاً، أو /64 في IPv6)، فلا يتجاوز الحد من يدوّر عناوين شبكته، ولا يحجب طلابُ مدرسة خلف
        // عنوان واحد يقرؤون كتباً مختلفة بعضَهم بعضاً. والعدّ بزيادة واحدة ذرية، و429 بموعد المحاولة (المشرف على #101، ١ و٢)
        $key = $limiter.'-reader:'.hash('sha256', $disk.'|'.$file).':'.NetworkPrefix::subscriber($request->ip());

        if (RateLimiter::increment($key, 60) > $perMinute) {
            throw new ThrottleRequestsException(headers: ['Retry-After' => RateLimiter::availableIn($key)]);
        }

        $secret = ReaderTicket::consume($request, $scope);
        abort_if($secret === null, 404);

        $request->attributes->set(SecurityHeaders::READER_ONLY, true);

        $storage = Storage::disk($disk);

        return new StreamedResponse(function () use ($storage, $file, $secret): void {
            $stream = $storage->readStream($file);

            if (! is_resource($stream)) {
                return;
            }

            $pad = str_repeat($secret, intdiv(self::CHUNK, strlen($secret)) + 1);
            $offset = 0;

            while (! feof($stream)) {
                $chunk = fread($stream, self::CHUNK);

                if ($chunk === false || $chunk === '') {
                    break;
                }

                $length = strlen($chunk);
                echo $chunk ^ substr($pad, $offset % strlen($secret), $length);
                $offset += $length;
                flush();
            }

            fclose($stream);
        }, 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Length' => (string) $storage->size($file),
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }
}
