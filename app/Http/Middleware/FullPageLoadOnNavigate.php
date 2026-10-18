<?php

namespace App\Http\Middleware;

use App\Support\SpaLink;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * التنقّل السريع في الواجهة العامة (SpaLink): يجلب Livewire الصفحة التالية ويضعها مكان الحالية. فإن ردّ الخادم
 * على طلب تنقّل بتحويل إلى ما يُحمَّل تحميلاً كاملاً (الدخول بعد انتهاء الجلسة، أو التحقق، أو تأكيد كلمة
 * المرور، أو مضيف آخر) أو بملف لا صفحة، صار الرد صفحة تحمّل الوجهة تحميلاً كاملاً. ورسالة التحويل تبقى في
 * الجلسة لتظهر فيها. وطلب تنقّل إلى مسار يُحمَّل كاملاً يُرد عليه بها دون متحكمه. وصفحة خطأ المتحكم (4xx) تُعطى
 * كما هي برمز 200.
 * ولوحة الإدارة لها مثله (LeavePanelWithFullPageLoad).
 */
class FullPageLoadOnNavigate
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasHeader('X-Livewire-Navigate')) {
            return $next($request);
        }

        // مسار يُحمَّل كاملاً (ملف، أو دخول، أو متجر) لا يُشغَّل متحكمه في طلب التنقّل، فلا يُسجَّل الاطلاع على ملف مرتين.
        // ومثله المسار الاحتياطي (تحويلات الموقع القديم، ورابط لا صفحة له): لا رابط في الموقع يتنقّل إليه (SpaLink)،
        // وطلب التنقّل إليه من لوحة الإدارة (رابط فيها لصفحة حُذفت) لا تُوضع صفحته العامة داخلها
        $route = $request->route();

        if (! SpaLink::currentPage($request) || ($route instanceof Route && $route->isFallback)) {
            return $this->fullPageLoad($request, $request->fullUrl());
        }

        $response = $next($request);

        // ومنه التحويل إلى مضيف آخر: يتبعه Livewire بطلب تمنعه سياسة connect-src فلا يحدث شيء. وأي صنف تحويل
        if ($response->isRedirection() && $response->headers->has('Location')) {
            $target = (string) $response->headers->get('Location');

            return SpaLink::allows($target) ? $response : $this->fullPageLoad($request, $target);
        }

        // ملف أو رد ليس صفحة من مسار فات FULL_PAGE_ROUTES: يُطلب من جديد تحميلاً كاملاً (احتياط). واختبار في
        // SpaNavigationTest يمنع متحكم صفحة يرد بملف، فلا يُشغَّل متحكم ملف مرتين
        if (! str_starts_with((string) $response->headers->get('Content-Type'), 'text/html')) {
            return $this->fullPageLoad($request, $request->fullUrl());
        }

        // صفحة خطأ المتحكم (خبر أُلغي نشره، أو رفض صلاحية) يأخذها Livewire برمز 200 فيضعها مكان الحالية، بلا طلب ثانٍ
        // للرابط نفسه: لا يُشغَّل المتحكم مرتين ولا يُحتسب الطلب مرتين في حدود throttle. ورمزها الحقيقي لطلب المتصفح
        // العادي (تحديث الصفحة). ولو بقي لحفظه Livewire، فصار كل رجوع بعدها تحويلاً إلى رابط مدخل السجل، أو إلى
        // /undefined لمدخل بلا حالة. ولا تحفظها ذاكرة المتصفح لطلب عادي برمز 200
        if ($this->inPlace($response)) {
            $response->setStatusCode(Response::HTTP_OK);
            $response->headers->set('Cache-Control', 'no-store');

            return $response;
        }

        return $response->getStatusCode() >= 400 ? $this->fullPageLoad($request, $request->fullUrl()) : $response;
    }

    /**
     * صفحة خطأ 4xx بقالب الموقع رُسمت داخل SecurityHeaders، فرمز الطلب فيها. وما سواها من الأخطاء تحميل كامل كما كان:
     * - خطأ الخادم (5xx) يصل إلى سجل nginx برمزه الحقيقي في طلب التحميل الكامل بعده (docs/monitoring.md)، وصفحة
     *   التصحيح المحلية لا تبقى أنماطها في رأس الصفحات التالية.
     * - تجاوز الحد (429) لا يعدّه الحد ولا يعمل فيه متحكم، فلا تكرار في طلبه الثاني، ولا تبقى صفحته في نسخة
     *   Livewire للرجوع بعد انقضاء المهلة.
     * - صفحة بلا رمز الطلب يضيف Livewire أنماطها إلى الرأس فتبقى للصفحات التالية: ما رُسم قبل SecurityHeaders (الربط
     *   الضمني للنموذج)، وصفحة Symfony الاحتياطية حين يتعطل قالب صفحة الخطأ نفسه.
     */
    private function inPlace(Response $response): bool
    {
        $nonce = Vite::cspNonce();

        return $response->isClientError()
            && $response->getStatusCode() !== Response::HTTP_TOO_MANY_REQUESTS
            && $response->headers->has('Content-Security-Policy')
            && $nonce !== null
            && str_contains((string) $response->getContent(), 'nonce="'.$nonce.'"');
    }

    /**
     * ورؤوس الحماية عليها كغيرها، فهذه الوسيطة تسبق SecurityHeaders في الترتيب (bootstrap/app.php).
     */
    private function fullPageLoad(Request $request, string $url): Response
    {
        return app(SecurityHeaders::class)->handle($request, fn (): Response => response()
            ->view('partials.full-page-load', ['url' => $url])
            ->header('Cache-Control', 'no-store'));
    }
}
