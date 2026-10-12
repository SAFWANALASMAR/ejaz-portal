<?php

use App\Http\Controllers\Account\FormEntriesController;
use App\Http\Controllers\Documents\FormEntryPrintController;
use App\Http\Middleware\AdminIdleTimeout;
use App\Http\Middleware\EnsureTwoFactorIsEnabled;
use Illuminate\Support\Facades\Route;

/*
| النماذج الإلكترونية خارج لوحة الإدارة (docs/plans/forms-print.md). يحمّله routes/web.php.
| الصفحات شخصية، فلا تحفظها ذاكرة المتصفح.
*/

// عرض الطباعة لمن يطّلع على المعبأ في اللوحة: بحراسة اللوحة نفسها (التحقق الثنائي ومدة الخمول)
Route::middleware(['auth', EnsureTwoFactorIsEnabled::class, AdminIdleTimeout::class, 'cache.headers:private;no_store'])
    ->prefix('documents/entries')->name('documents.entries.')
    ->group(function () {
        Route::get('/{entry:uuid}/print', [FormEntryPrintController::class, 'show'])->middleware('throttle:60,1')->name('print');
        Route::get('/{entry:uuid}/pdf', [FormEntryPrintController::class, 'pdf'])->middleware('throttle:20,1')->name('pdf');
    });

// «نماذجي»: المعتمد من نماذج المشاركين المحفوظة في ملف صاحب الحساب
Route::middleware(['auth', 'cache.headers:private;no_store'])->prefix('account/forms')->name('account.forms.')->group(function () {
    Route::get('/', [FormEntriesController::class, 'index'])->name('index');
    Route::get('/{entry:uuid}', [FormEntriesController::class, 'show'])->middleware('throttle:60,1')->name('show');
    Route::get('/{entry:uuid}/pdf', [FormEntriesController::class, 'pdf'])->middleware('throttle:20,1')->name('pdf');
});
