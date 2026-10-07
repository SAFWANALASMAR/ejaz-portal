<?php

use App\Http\Controllers\Account\PaymentsController;
use App\Http\Controllers\Payments\DonationController;
use App\Http\Controllers\Payments\FakeCheckoutController;
use App\Http\Controllers\Payments\PaymentReturnController;
use App\Http\Controllers\Payments\PaymentWebhookController;
use App\Http\Controllers\StoreController;
use Illuminate\Support\Facades\Route;

/*
| المتجر والدفع والتبرعات (المرحلة 5 — docs/plans/store-payments.md). تُحمَّل من routes/web.php.
*/

Route::get('/store', StoreController::class)->name('store.index');

Route::get('/donate', [DonationController::class, 'create'])->name('donate');
Route::post('/donate', [DonationController::class, 'store'])->middleware('throttle:10,1')->name('donate.store');

// العملية في الروابط بمعرّفها العشوائي لا برقمها
Route::get('/payments/{payment:uuid}/return', PaymentReturnController::class)->middleware('throttle:30,1')->name('payments.return');

// صفحة الدفع التجريبية للبوابة الوهمية (البيئة المحلية فقط، والمتحكم يرد 404 في غيرها)
Route::get('/payments/{payment:uuid}/fake-checkout', [FakeCheckoutController::class, 'show'])->name('payments.fake-checkout');
Route::post('/payments/{payment:uuid}/fake-checkout', [FakeCheckoutController::class, 'pay'])->name('payments.fake-checkout.pay');
Route::post('/payments/{payment:uuid}/fake-checkout/fail', [FakeCheckoutController::class, 'fail'])->name('payments.fake-checkout.fail');

// إشعار بوابة الدفع: مستثنى من CSRF (bootstrap/app.php) ويفحص توقيعه بنفسه
Route::post('/webhooks/payments', PaymentWebhookController::class)->middleware('throttle:120,1')->name('payments.webhook');

Route::middleware('auth')->get('/account/payments', PaymentsController::class)->name('account.payments');
