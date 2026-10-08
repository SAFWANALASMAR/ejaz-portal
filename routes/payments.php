<?php

use App\Http\Controllers\Account\PaymentsController;
use App\Http\Controllers\Payments\DonationController;
use App\Http\Controllers\Payments\FakeCheckoutController;
use App\Http\Controllers\Payments\PaymentReturnController;
use App\Http\Controllers\Payments\PaymentWebhookController;
use App\Http\Controllers\StoreController;
use App\Http\Middleware\EnsureAccountIsActive;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/*
| المتجر والدفع والتبرعات (المرحلة 5 — docs/plans/store-payments.md). تُحمَّل من routes/web.php.
*/

Route::get('/store', StoreController::class)->name('store.index');

Route::get('/donate', [DonationController::class, 'create'])->name('donate');
// كل إرسال ينشئ سجلاً دائماً وفاتورة عند البوابة، فالحد منخفض، ومعه سقف عام في الساعة (StartDonation)
Route::post('/donate', [DonationController::class, 'store'])->middleware('throttle:5,1')->name('donate.store');

// العملية في الروابط بمعرّفها العشوائي لا برقمها
Route::get('/payments/{payment:uuid}/return', PaymentReturnController::class)->middleware('throttle:30,1')->name('payments.return');

// صفحة الدفع التجريبية للبوابة الوهمية: في البيئتين المحلية والاختبارية وحدهما، ولا مسار لها في غيرهما
if (app()->environment('local', 'testing')) {
    Route::get('/payments/{payment:uuid}/fake-checkout', [FakeCheckoutController::class, 'show'])->name('payments.fake-checkout');
    Route::post('/payments/{payment:uuid}/fake-checkout', [FakeCheckoutController::class, 'pay'])->name('payments.fake-checkout.pay');
    Route::post('/payments/{payment:uuid}/fake-checkout/fail', [FakeCheckoutController::class, 'fail'])->name('payments.fake-checkout.fail');
}

// إشعار بوابة الدفع: من خادمها بلا متصفح، فلا جلسة ولا كعكات ولا CSRF (وهو مستثنى منه صراحة في
// bootstrap/app.php أيضاً)، ويفحص توقيعه بنفسه
Route::post('/webhooks/payments', PaymentWebhookController::class)
    ->middleware('throttle:60,1')
    ->withoutMiddleware([EncryptCookies::class, AddQueuedCookiesToResponse::class, StartSession::class, ShareErrorsFromSession::class, PreventRequestForgery::class, EnsureAccountIsActive::class])
    ->name('payments.webhook');

Route::middleware('auth')->get('/account/payments', PaymentsController::class)->name('account.payments');
