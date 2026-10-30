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
 * وFilament تضيفه تلقائياً. و'unsafe-eval' للوحة الإدارة وحدها لأن تعابير Alpine فيها تحتاجه، والواجهة
 * العامة بلا تعابير Alpine فلا تحتاجه وإن حمّلت Livewire للتنقّل السريع (docs/decisions.md). وكذلك
 * worker-src لمعاينات الصور في نماذج اللوحة.
 */
class SecurityHeaders
{
    /**
     * علامة في الطلب يضعها LibraryController لملف كتاب «للقراءة فقط».
     */
    public const READER_ONLY = 'security.reader_only';

    public function handle(Request $request, Closure $next): Response
    {
        Vite::useCspNonce();

        $response = $next($request);

        // ملف PDF من الحوكمة أو المكتبة يُعرض مدمجاً في صفحته، فيُقبل تضمينه من الموقع نفسه وحده.
        // وسياسته سطر التضمين وحده، حتى لا تعطل عارض PDF في المتصفح. والاستثناء لملف PDF وحده،
        // فصفحة الخطأ وملف الصوت على المسار نفسه يأخذان السياسة الكاملة. وكذلك كتاب «للقراءة فقط» (READER_ONLY):
        // يقرؤه قارئ الصفحة بطلب fetch، فلا يُضمَّن في إطار، ولا يعمل عليه عارض المتصفح بزري التنزيل والطباعة
        if ($request->routeIs('governance.file', 'library.file') && $response->headers->get('Content-Type') === 'application/pdf'
            && $request->attributes->get(self::READER_ONLY) !== true) {
            $response->headers->set('Content-Security-Policy', "frame-ancestors 'self'");
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        } else {
            $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy($request));
            $response->headers->set('X-Frame-Options', 'DENY');
        }

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        // معرض الشواهد: الرمز السري في الرابط، فلا يُرسل في Referer إلى الصور وروابط التخزين الموقّعة ولا إلى ما يُفتح منه،
        // ولا تُفهرس الصفحة ولا ملفاتها ولا رد الرفض (docs/decisions.md — 30 أكتوبر 2026)
        if ($request->routeIs('evidence.*')) {
            $response->headers->set('Referrer-Policy', 'no-referrer');
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
        } else {
            $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        }
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        // عزل النافذة عن صفحات المواقع الأخرى التي تفتحها، ومنع تضمين ردودنا في مواقع أخرى (فحص ZAP،
        // docs/security/). ولا Cross-Origin-Embedder-Policy: يمنع تضمين مقاطع يوتيوب
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');

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
            'script-src' => ["'self'", $nonce, $request->routeIs('filament.*') ? "'unsafe-eval'" : null, $dev],
            'style-src' => ["'self'", "'unsafe-inline'", $dev],
            // الصور من الموقع نفسه، ونطاقات التخزين وشبكة التوصيل صراحةً من CSP_IMG_SRC
            'img-src' => ["'self'", 'data:', 'blob:', ...self::origins('img_src')],
            'font-src' => ["'self'", 'data:', $dev],
            'connect-src' => ["'self'", $dev, $devSocket, ...self::origins('connect_src')],
            'media-src' => ["'self'", 'blob:', ...self::origins('media_src')],
            // 'self' لعرض ملف الوثيقة داخل صفحتها، ونطاق التخزين متى حُوّل الملف إلى رابطه الموقّع. وفي كل صفحة لا في
            // صفحتي العارض وحدهما: التنقّل السريع يُبقي سياسة أول صفحة حُمّلت، فلا تصل سياسة صفحة إلا بتحميلها كاملاً
            // (docs/decisions.md — 19 أكتوبر 2026)
            'frame-src' => ["'self'", ...config('security.csp.frame_src'), ...self::origins('storage_frame_src')],
            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'", ...self::origins('form_action')],
            'frame-ancestors' => ["'none'"],
        ];

        // أداة رفع الملفات في اللوحة (FilePond في Filament) ترسم معاينة الصورة في عامل من blob:، وبلا worker-src يُفحص
        // العامل بـ script-src فيُحجب. فللوحة وحدها، ولا يتسع script-src ولا سياسة الواجهة العامة (docs/decisions.md)
        if ($request->routeIs('filament.*')) {
            $directives['worker-src'] = ["'self'", 'blob:'];
        } else {
            // وفي الواجهة العامة عامل قارئ الكتب (pdf.js) من أصول البناء على الموقع نفسه وحده، أضيق من الرجوع إلى
            // script-src. وفي كل صفحة لأن التنقّل السريع يُبقي سياسة أول صفحة. وخادم Vite في التطوير أصل آخر، فيلفّ
            // pdf.js العامل في blob: (docs/decisions.md — 30 أكتوبر 2026)
            $directives['worker-src'] = ["'self'", $dev ? 'blob:' : null, $dev];
        }

        // عنصر التحدي ضد الآليات في صفحات نماذجه وحدها، متى كان مشغّله turnstile، فلا يتسع ما تسمح به الصفحات الأخرى
        // (docs/decisions.md): الاستبيان والتبرع، والدخول والتسجيل والاستعادة (قرار صفوان، 20 أكتوبر 2026)
        if (config('services.bot_challenge.driver') === 'turnstile' && $request->routeIs('surveys.show', 'donate', 'login', 'register', 'password.request', 'competitions.student.register')) {
            $directives['script-src'] = [...$directives['script-src'], ...config('security.csp.challenge_src')];
            $directives['frame-src'] = [...$directives['frame-src'], ...config('security.csp.challenge_src')];
        }

        // لوحة Horizon (للمشرف العام فقط) تضمّن سكربتاتها وخطوطها دون رمز
        $horizon = trim(config('horizon.path'), '/');
        if ($request->is($horizon, $horizon.'/*')) {
            $directives['script-src'] = ["'self'", "'unsafe-inline'", "'unsafe-eval'"];
            $directives['style-src'] = ["'self'", "'unsafe-inline'", 'https://fonts.bunny.net'];
            $directives['font-src'] = ["'self'", 'data:', 'https://fonts.bunny.net'];
        }

        return collect($directives)
            ->map(fn (array $sources, string $name): string => trim($name.' '.implode(' ', array_filter($sources))))
            ->implode('; ');
    }

    /**
     * نطاقات من متغيرات البيئة (CSP_IMG_SRC وأخواتها): أصل https صريح وحده (نطاق، ومنفذ اختياري)، بلا مسار،
     * والشرطة الأخيرة المنسوخة من لوحة المزود تُحذف. فلا يوسّع خطأ في الإعداد السياسة كلها: «*» و«https:»
     * و«'unsafe-inline'» وما شابهها تُهمل. ورابط wss للاتصال وحده.
     *
     * وبادئة *. للنطاقات الفرعية في الصور والوسائط وحدها: في الاتصال والنماذج تفتح حاويات كل عملاء المزود
     * قناةً لتسريب البيانات بعد أي حقن، فيُضبط نطاق الحاوية بعينه.
     *
     * @return list<string>
     */
    private static function origins(string $key): array
    {
        $scheme = $key === 'connect_src' ? '(https|wss)' : 'https';
        $wildcard = in_array($key, ['img_src', 'media_src'], true) ? '(\*\.)?' : '';

        return array_values(array_filter(
            array_map(fn (mixed $origin): string => rtrim(strtolower(trim((string) $origin)), '/'), (array) config('security.csp.'.$key)),
            fn (string $origin): bool => preg_match('#^'.$scheme.'://'.$wildcard.'[a-z0-9-]+(\.[a-z0-9-]+)+(:\d{1,5})?$#', $origin) === 1,
        ));
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
