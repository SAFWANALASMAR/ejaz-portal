<?php

use App\Http\Controllers\Account\ProgramRegistrationController;
use App\Http\Controllers\ProgramController;
use Illuminate\Support\Facades\Route;

/*
| البرامج والدورات (requirements/03): «برامجنا» بتبويبات الأنواع، وصفحة البرنامج، والتسجيل
| فيه، و«برامجي» في لوحة المستخدم. يحمّله routes/web.php.
*/

Route::get('/programs', [ProgramController::class, 'index'])->name('programs.index');

Route::where(['slug' => '[a-z0-9]+(?:-[a-z0-9]+)*'])->group(function () {
    Route::get('/programs/{slug}', [ProgramController::class, 'show'])->name('programs.show');

    // التسجيل بحساب موثق البريد
    Route::middleware(['auth', 'verified'])->group(function () {
        Route::get('/programs/{slug}/register', [ProgramRegistrationController::class, 'create'])->name('programs.register');
        Route::post('/programs/{slug}/register', [ProgramRegistrationController::class, 'store'])->middleware('throttle:10,1')->name('programs.register.store');
    });
});

// برامجي
Route::middleware('auth')->prefix('account/programs')->name('account.programs.')->group(function () {
    Route::get('/', [ProgramRegistrationController::class, 'index'])->name('index');
    Route::post('/{registration:uuid}/cancel', [ProgramRegistrationController::class, 'cancel'])->middleware('throttle:10,1')->name('cancel');
});
