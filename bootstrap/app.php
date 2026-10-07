<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

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

        // إشعارات بوابة الدفع تأتي من خادمها بلا جلسة، ويُفحص توقيعها في الإجراء (docs/security.md)
        $middleware->validateCsrfTokens(except: ['webhooks/*']);

        $middleware->web(append: [
            SecurityHeaders::class,
            EnsureAccountIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
