<?php

use App\Http\Controllers\Account\DashboardController;
use App\Http\Controllers\Account\DataRightsController;
use App\Http\Controllers\Account\PhoneVerificationController;
use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\Account\SecurityController;
use Illuminate\Support\Facades\Route;

/*
| مسارات الدخول والتسجيل والاستعادة والتحقق الثنائي يسجلها Fortify (config/fortify.php).
*/

Route::view('/', 'home')->name('home');

// لوحة المستخدم
Route::middleware('auth')->prefix('account')->name('account.')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile');
    Route::get('/security', SecurityController::class)->middleware('password.confirm')->name('security');

    Route::get('/verify-phone', [PhoneVerificationController::class, 'show'])->name('phone.verify');
    Route::post('/verify-phone', [PhoneVerificationController::class, 'verify'])->middleware('throttle:10,1')->name('phone.verify.store');
    Route::post('/verify-phone/send', [PhoneVerificationController::class, 'send'])->middleware('throttle:6,1')->name('phone.send');

    Route::get('/data', [DataRightsController::class, 'show'])->name('data');
    Route::get('/data/export', [DataRightsController::class, 'export'])->middleware(['password.confirm', 'throttle:5,60'])->name('data.export');
    Route::post('/data/deletion', [DataRightsController::class, 'requestDeletion'])->middleware('throttle:5,60')->name('data.deletion');
});
