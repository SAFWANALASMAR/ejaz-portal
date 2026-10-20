<?php

use App\Http\Middleware\CountVisit;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\FullPageLoadOnNavigate;
use App\Http\Middleware\RejectInvalidUtf8;
use App\Http\Middleware\RequirePasswordUpgrade;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // طلب بمضيف غير نطاق البوابة (APP_URL ونطاقاته الفرعية) يُرفض برد 400.
        // لارافيل لا يفعّله في البيئة المحلية والاختبارات
        $middleware->trustHosts();

        // إشعارات بوابة الدفع وحالة تسليم واتساب تأتي من خادمها بلا جلسة، ويُفحص توقيعها في الإجراء (docs/security.md)
        $middleware->validateCsrfTokens(except: ['webhooks/payments', 'webhooks/whatsapp']);

        // تغيير كلمة المرور أو إعادة تعيينها يُنهي جلسات الحساب الأخرى في الموقع كله، لا في اللوحة وحدها
        // (docs/security/2026-10-10-security-audit.md). وجلسة من غيّرها تبقى، لأن البصمة الجديدة تُحفظ فيها.
        // وعدّاد الزيارات بعد FullPageLoadOnNavigate، فلا يُعدّ طلب تنقّل يصير تحميلاً كاملاً إلا بتحميله.
        // ورؤوس الحماية أول المجموعة: تحيط بكل ما بعدها، فتصل إلى ما يُرسم قبل المتحكم أيضاً (تجاوز الحد، والربط الضمني
        // للنموذج، ورمز الحماية المنتهي)، برمز الطلب. ولوحة الإدارة لها مجموعتها، وفيها SecurityHeaders في أولها.
        // والمدخل بترميز غير صالح يُرفض بعدها وقبل الجلسة (RejectInvalidUtf8)
        // وكلمة مرور قائمة لا تطابق السياسة تُغيَّر من «الأمان» قبل أي صفحة أخرى (RequirePasswordUpgrade)
        $middleware->web(prepend: [SecurityHeaders::class, RejectInvalidUtf8::class], append: [
            AuthenticateSession::class,
            FullPageLoadOnNavigate::class,
            EnsureAccountIsActive::class,
            RequirePasswordUpgrade::class,
            CountVisit::class,
        ]);

        // التنقّل السريع إلى ما يُحمَّل كاملاً تحميل كامل (FullPageLoadOnNavigate)، ومنه تحويل الدخول من Authenticate:
        // فيُقدَّم عليه في ترتيب الأولوية، وإلا قدّم لارافيل Authenticate على كل ما أُلحق بمجموعة web
        $middleware->prependToPriorityList(before: AuthenticatesRequests::class, prepend: FullPageLoadOnNavigate::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // خطأ القاعدة يُسجَّل بلا قيم الاستعلام: رسالته تحمل القيم المربوطة (الاسم والبريد والجوال وبصمة كلمة المرور)،
        // ونص PostgreSQL نفسه قد يحمل قيمة المدخل (23505 في DETAIL، و22P02 «invalid input syntax for type uuid: "…"»).
        // فيُسجَّل رمز الخطأ SQLSTATE وحده، والاستعلام بعلاماته، وموضعه، واسم المسار لا المسار نفسه (فيه رموز مثل
        // reset-password/{token}) (docs/security.md: لا كلمات مرور ولا بيانات هوية في السجلات — اختبار الأمن قبل الرفع،
        // ومراجعة #61). والتشخيص بالرمز والاستعلام والموضع، وبإعادة الطلب في التطوير
        $exceptions->report(function (QueryException $exception): false {
            $frame = collect($exception->getTrace())->first(fn (array $frame) => str_starts_with($frame['file'] ?? '', app_path()));

            Log::error('Database query failed', [
                'sqlstate' => $exception->errorInfo[0] ?? $exception->getCode(),
                'connection' => $exception->getConnectionName(),
                'sql' => $exception->getSql(),
                'at' => isset($frame['file']) ? $frame['file'].':'.($frame['line'] ?? 0) : null,
                'route' => app()->runningInConsole() ? null : request()->route()?->getName(),
            ]);

            return false;
        });
    })->create();
