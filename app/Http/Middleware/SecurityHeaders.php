<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * رؤوس الحماية الموحدة لكل استجابات الويب (docs/security.md).
 *
 * السكربتات المضمنة لا تعمل إلا برمز (nonce) يتولد لكل طلب، وVite وLivewire
 * وFilament تضيفه تلقائياً. ويبقى 'unsafe-eval' لأن Alpine يحتاجه.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        Vite::useCspNonce();

        $response = $next($request);

        // ملف PDF من الحوكمة أو المكتبة يُعرض مدمجاً في صفحته، فيُقبل تضمينه من الموقع نفسه وحده.
        // وسياسته سطر التضمين وحده، حتى لا تعطل عارض PDF في المتصفح. والاستثناء لملف PDF وحده،
        // فصفحة الخطأ وملف الصوت على المسار نفسه يأخذان السياسة الكاملة
        if ($request->routeIs('governance.file', 'library.file') && $response->headers->get('Content-Type') === 'application/pdf') {
            $response->headers->set('Content-Security-Policy', "frame-ancestors 'self'");
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        } else {
            $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy($request));
            $response->headers->set('X-Frame-Options', 'DENY');
        }

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        if ($request->isSecure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age='.config('security.hsts_max_age').'; includeSubDomains',
            );
        }

        return $response;
    }

    private function contentSecurityPolicy(Request $request): string
    {
        $nonce = "'nonce-".Vite::cspNonce()."'";
        $dev = $this->viteDevServer();
        $devSocket = $dev ? preg_replace('/^http/', 'ws', $dev) : null;

        $directives = [
            'default-src' => ["'self'"],
            'script-src' => ["'self'", $nonce, "'unsafe-eval'", $dev],
            'style-src' => ["'self'", "'unsafe-inline'", $dev],
            'img-src' => ["'self'", 'data:', 'blob:', 'https:'],
            'font-src' => ["'self'", 'data:', $dev],
            'connect-src' => ["'self'", $dev, $devSocket, ...config('security.csp.connect_src')],
            'media-src' => ["'self'", 'blob:', ...config('security.csp.media_src')],
            // 'self' لعرض ملف الوثيقة داخل صفحتها
            'frame-src' => ["'self'", ...config('security.csp.frame_src')],
            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'", ...config('security.csp.form_action')],
            'frame-ancestors' => ["'none'"],
        ];

        // عنصر التحدي ضد الآليات في صفحتي الاستبيان والتبرع وحدهما، متى كان مشغّله turnstile، فلا يتسع ما تسمح به
        // الصفحات الأخرى (docs/decisions.md)
        if (config('services.bot_challenge.driver') === 'turnstile' && $request->routeIs('surveys.show', 'donate')) {
            $directives['script-src'] = [...$directives['script-src'], ...config('security.csp.challenge_src')];
            $directives['frame-src'] = [...$directives['frame-src'], ...config('security.csp.challenge_src')];
        }

        // لوحة Horizon (للمشرف العام فقط) تضمّن سكربتاتها وخطوطها دون رمز
        if ($request->is(trim(config('horizon.path'), '/').'*')) {
            $directives['script-src'] = ["'self'", "'unsafe-inline'", "'unsafe-eval'"];
            $directives['style-src'] = ["'self'", "'unsafe-inline'", 'https://fonts.bunny.net'];
            $directives['font-src'] = ["'self'", 'data:', 'https://fonts.bunny.net'];
        }

        return collect($directives)
            ->map(fn (array $sources, string $name): string => trim($name.' '.implode(' ', array_filter($sources))))
            ->implode('; ');
    }

    /**
     * عنوان خادم Vite في التطوير، ليُسمح بملفاته وتحديثاته الحية.
     */
    private function viteDevServer(): ?string
    {
        if (! app()->isLocal() || ! Vite::isRunningHot()) {
            return null;
        }

        return rtrim((string) file_get_contents(Vite::hotFile()), "/ \n");
    }
}
