<?php

namespace App\Http\Middleware;

use App\Support\Visits;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * عدّاد الزيارات البسيط (Visits): يعدّ صفحة الموقع التي يطلبها المتصفح، بتحميل كامل أو بالتنقّل السريع
 * (wire:navigate)، إذا ردّ عليها الخادم بنجاح بصفحة HTML.
 *
 * ولا يعدّ: لوحة الإدارة، وطلبات Livewire وHorizon، والجلب المسبق، والملفات والتنزيلات، والتحويلات والأخطاء،
 * ولا ما ليس متصفحاً، ولا الزواحف التي تعلن اسمها في وكيل المتصفح (bot وcrawl وspider ونحوها). والصفحة التي يعرضها
 * المتصفح من جلبه المسبق لا تُعدّ، لأنه لا يطلبها ثانية: نقص مقبول في عدّاد تقريبي.
 *
 * وهو في مجموعة web بعد FullPageLoadOnNavigate، فطلب التنقّل الذي يصير تحميلاً كاملاً لا يُعدّ إلا بتحميله.
 */
class CountVisit
{
    /**
     * رؤوس الجلب المسبق في المتصفحات: Sec-Purpose (قواعد التخمين)، وPurpose وX-Purpose وX-Moz (القديمة).
     */
    private const PREFETCH_HEADERS = ['Sec-Purpose', 'Purpose', 'X-Purpose', 'X-Moz'];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->isPageView($request, $response)) {
            try {
                Visits::record($request);
            } catch (Throwable $exception) {
                // العدّاد لا يُسقط صفحة
                report($exception);
            }
        }

        return $response;
    }

    private function isPageView(Request $request, Response $response): bool
    {
        if (! $request->isMethod('GET') || $response->getStatusCode() !== Response::HTTP_OK) {
            return false;
        }

        if ($response instanceof BinaryFileResponse || $response instanceof StreamedResponse || $response->headers->has('Content-Disposition')
            || ! str_starts_with((string) $response->headers->get('Content-Type'), 'text/html')) {
            return false;
        }

        // ومسار تحديث Livewire اسمه default-livewire.update. وطباعة النموذج المعبأ من لوحة الإدارة (documents.entries)
        // صفحة HTML خارج /admin، وهي من اللوحة لا من الموقع
        if ($request->routeIs('filament.*', 'horizon.*', '*livewire.*', 'documents.entries.*')) {
            return false;
        }

        // التنقّل السريع يجلب الصفحة من شيفرتها (Sec-Fetch-Dest: empty)، وما سواه من الجلب ليس مشاهدة
        $destination = $request->header('Sec-Fetch-Dest');

        if ($destination !== null && $destination !== 'document' && ! $request->hasHeader('X-Livewire-Navigate')) {
            return false;
        }

        foreach (self::PREFETCH_HEADERS as $header) {
            if (preg_match('/prefetch|preview/i', (string) $request->header($header)) === 1) {
                return false;
            }
        }

        $agent = (string) $request->userAgent();

        return str_contains($agent, 'Mozilla/') && preg_match('/bot|crawl|spider|slurp|headless|lighthouse|inspectiontool|externalagent|fetcher|monitor|preview/i', $agent) !== 1;
    }
}
