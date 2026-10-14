<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * لوحة الإدارة تتنقل بلا إعادة تحميل (SPA): يجلب Livewire الصفحة التالية ويضعها مكان الحالية أياً كان
 * رمز حالتها. فإن ردّ الخادم بتحويل خارج اللوحة (انتهاء الخمول، أو التحقق الثنائي، أو انتهاء الجلسة، أو
 * إيقاف الحساب) أو بصفحة خطأ (سجل حُذف، أو صفحة ممنوعة) وُضعت صفحة بقالب الموقع العام داخل قالب اللوحة
 * وأصولها. فهذه الردود في طلب تنقّل تصير صفحة تحمّل الوجهة تحميلاً كاملاً: وجهة التحويل، أو الصفحة
 * نفسها فيعرض الخادم خطأها بقالبه. ورسالة التحويل تبقى في الجلسة لتظهر فيها.
 */
class LeavePanelWithFullPageLoad
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->hasHeader('X-Livewire-Navigate')) {
            return $response;
        }

        if ($response->getStatusCode() >= 400) {
            return $this->fullPageLoad($request->fullUrl());
        }

        if (! $response instanceof RedirectResponse) {
            return $response;
        }

        $target = $response->getTargetUrl();
        $panel = url(Filament::getCurrentOrDefaultPanel()->getPath());

        // داخل اللوحة يبقى التنقّل، ووجهة على مضيف آخر تبقى تحويلاً عادياً
        if (Str::is([$panel, $panel.'/*', $panel.'?*', $panel.'#*'], $target)
            || parse_url($target, PHP_URL_HOST) !== $request->getHost()) {
            return $response;
        }

        return $this->fullPageLoad($target);
    }

    private function fullPageLoad(string $url): Response
    {
        return response()
            ->view('filament.full-page-redirect', ['url' => $url])
            ->header('Cache-Control', 'no-store');
    }
}
