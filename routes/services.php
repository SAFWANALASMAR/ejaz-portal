<?php

use App\Http\Controllers\Account\ServiceRequestController;
use App\Http\Controllers\ServicesController;
use Illuminate\Support\Facades\Route;

/*
| الخدمات الإلكترونية (requirements/02): صفحة الخدمات، والفرص بأنواعها، والتقديم عليها،
| و«طلباتي» في لوحة المستخدم. يحمّله routes/web.php.
*/

Route::get('/services', [ServicesController::class, 'index'])->name('services.index');
Route::get('/services/assembly-membership', [ServicesController::class, 'membership'])->name('services.assembly-membership');
Route::get('/services/jobs', [ServicesController::class, 'jobs'])->name('services.jobs');
Route::get('/services/volunteering', [ServicesController::class, 'volunteering'])->name('services.volunteering');

Route::where(['slug' => '[a-z0-9]+(?:-[a-z0-9]+)*'])->group(function () {
    Route::get('/services/openings/{slug}', [ServicesController::class, 'show'])->name('services.openings.show');

    // التقديم بحساب موثق البريد
    Route::middleware(['auth', 'verified'])->group(function () {
        Route::get('/services/openings/{slug}/apply', [ServiceRequestController::class, 'create'])->name('services.apply');
        Route::post('/services/openings/{slug}/apply', [ServiceRequestController::class, 'store'])->middleware('throttle:10,1')->name('services.apply.store');
    });
});

// طلباتي
Route::middleware(['auth', 'cache.headers:private;no_store'])->prefix('account/requests')->name('account.requests.')->group(function () {
    Route::get('/', [ServiceRequestController::class, 'index'])->name('index');
    Route::get('/{serviceRequest:uuid}', [ServiceRequestController::class, 'show'])->name('show');
    Route::put('/{serviceRequest:uuid}', [ServiceRequestController::class, 'update'])->middleware(['verified', 'throttle:10,1'])->name('update');
    Route::get('/{serviceRequest:uuid}/files/{key}', [ServiceRequestController::class, 'file'])
        ->where('key', '[a-z0-9_]+')->middleware('throttle:60,1')->name('file');
});
