<?php

namespace App\Http\Middleware;

use App\Support\SpaLink;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * التنقّل السريع في الواجهة العامة (SpaLink): يجلب Livewire الصفحة التالية ويضعها مكان الحالية. فإن ردّ الخادم
 * على طلب تنقّل بتحويل إلى ما يُحمَّل تحميلاً كاملاً (الدخول بعد انتهاء الجلسة، أو التحقق، أو تأكيد كلمة
 * المرور، أو مضيف آخر) أو بملف لا صفحة، صار الرد صفحة تحمّل الوجهة تحميلاً كاملاً. ورسالة التحويل تبقى في
 * الجلسة لتظهر فيها. وطلب تنقّل إلى مسار يُحمَّل كاملاً يُرد عليه بها دون متحكمه.
 * ولوحة الإدارة لها مثله (LeavePanelWithFullPageLoad).
 */
class FullPageLoadOnNavigate
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasHeader('X-Livewire-Navigate')) {
            return $next($request);
        }

        // مسار يُحمَّل كاملاً (ملف، أو دخول، أو متجر) لا يُشغَّل متحكمه في طلب التنقّل، فلا يُسجَّل الاطلاع على ملف مرتين
        if (! SpaLink::currentPage($request)) {
            return $this->fullPageLoad($request, $request->fullUrl());
        }

        $response = $next($request);

        // ومنه التحويل إلى مضيف آخر: يتبعه Livewire بطلب تمنعه سياسة connect-src فلا يحدث شيء. وأي صنف تحويل
        if ($response->isRedirection() && $response->headers->has('Location')) {
            $target = (string) $response->headers->get('Location');

            return SpaLink::allows($target) ? $response : $this->fullPageLoad($request, $target);
        }

        // صفحة الخطأ (خبر أُلغي نشره، أو رفض صلاحية) تُحمَّل كاملة: Livewire يحفظ رمز حالتها، فيصير كل رجوع بعدها
        // تحويلاً إلى رابط مدخل السجل، فيبقى الخطأ تحت عنوان صفحة سابقة أو يذهب إلى /undefined (مدخل بلا حالة)
        if ($response->getStatusCode() >= 400) {
            return $this->fullPageLoad($request, $request->fullUrl());
        }

        // ملف أو رد ليس صفحة من مسار فات FULL_PAGE_ROUTES: يُطلب من جديد تحميلاً كاملاً (احتياط)
        if ($response->isSuccessful() && ! str_starts_with((string) $response->headers->get('Content-Type'), 'text/html')) {
            return $this->fullPageLoad($request, $request->fullUrl());
        }

        return $response;
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
