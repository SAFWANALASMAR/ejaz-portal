<?php

use App\Http\Controllers\WhatsAppWebhookController;
use App\Http\Middleware\EnsureAccountIsActive;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/*
| قنوات التواصل (docs/guides/messaging.md). تُحمَّل من routes/web.php.
| إشعارات حالة التسليم من خادم Meta بلا متصفح: لا جلسة ولا كعكات ولا CSRF (ومستثناة منه صراحة في
| bootstrap/app.php)، والتوقيع يُفحص في HandleWhatsAppWebhook.
*/

// Meta ترسل من عناوين قليلة، وحتى ثلاثة إشعارات لكل رسالة، فالحد واسع، والرفض لا يُسجَّل إلا مرة لكل عنوان
Route::middleware('throttle:1000,1')
    ->withoutMiddleware([EncryptCookies::class, AddQueuedCookiesToResponse::class, StartSession::class, ShareErrorsFromSession::class, PreventRequestForgery::class, EnsureAccountIsActive::class])
    ->group(function () {
        Route::get('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify'])->name('messaging.whatsapp.verify');
        Route::post('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'receive'])->name('messaging.whatsapp.webhook');
    });
