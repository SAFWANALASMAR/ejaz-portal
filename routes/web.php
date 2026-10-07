<?php

use App\Http\Controllers\Account\DashboardController;
use App\Http\Controllers\Account\DataRightsController;
use App\Http\Controllers\Account\PhoneVerificationController;
use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\Account\SecurityController;
use App\Http\Controllers\GovernanceController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InitiativeController;
use App\Http\Controllers\PageController;
use Illuminate\Support\Facades\Route;

/*
| مسارات الدخول والتسجيل والاستعادة والتحقق الثنائي يسجلها Fortify (config/fortify.php).
*/

Route::get('/', HomeController::class)->name('home');

// المحتوى المؤسسي
Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/pages/{slug}', [PageController::class, 'show'])
    ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')
    ->name('pages.show');
Route::get('/governance', [GovernanceController::class, 'index'])->name('governance.index');
Route::get('/governance/{slug}', [GovernanceController::class, 'show'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('governance.show');
// الملف يُبث من الخادم، فحد معقول لكل عنوان. وفي الإنتاج يُسلَّم للخادم أو لرابط تخزين موقّع (roadmap.md)
Route::middleware('throttle:60,1')->group(function () {
    Route::get('/governance/{slug}/file', [GovernanceController::class, 'file'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('governance.file');
    Route::get('/governance/{slug}/download', [GovernanceController::class, 'download'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('governance.download');
});
Route::get('/initiatives', [InitiativeController::class, 'index'])->name('initiatives.index');
Route::get('/initiatives/{slug}', [InitiativeController::class, 'show'])
    ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')
    ->name('initiatives.show');

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
