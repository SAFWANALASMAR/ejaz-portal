<?php

use App\Http\Controllers\Assembly\AssemblyController;
use App\Http\Controllers\Assembly\AssemblyFileController;
use App\Http\Controllers\Assembly\MeetingController;
use App\Http\Middleware\EnsureTwoFactorIsEnabled;
use Illuminate\Support\Facades\Route;

/*
| الجمعية العمومية (المرحلة 4 — docs/plans/assembly.md). تُحمَّل من routes/web.php.
| صفحة القسم عامة، وما سواها لمن يصل إلى منطقة الأعضاء وحده (AssemblyArea)، بلا حفظ في ذاكرة المتصفح.
| والتنزيل برابط مؤقت موقّع، ويُفحص الدخول والصلاحية عند الطلب، ويُسجَّل.
*/

// والصفحة نفسها تعرض لعضو المنطقة روابط الاجتماعات وقرارات المجلس (BuildBoardPortal)، فخلف التحقق الثنائي لأدواره كبقية المنطقة.
// والوسيط لا يمس الزائر ولا من لا يلزمه التحقق الثنائي (المراجعة الأمنية على #197)
Route::get('/assembly', AssemblyController::class)->middleware(EnsureTwoFactorIsEnabled::class)->name('assembly.index');

// والأدوار الإدارية (المجلس والسكرتير والمشرف العام) لا تصل إلى ملفات المجلس ومحاضره قبل تفعيل التحقق الثنائي، كما في اللوحة
// (docs/security/2026-10-10-security-audit.md)
Route::middleware(['auth', EnsureTwoFactorIsEnabled::class, 'can:accessAssemblyArea', 'cache.headers:private;no_store'])->prefix('assembly')->name('assembly.')->group(function () {
    Route::get('/meetings', [MeetingController::class, 'index'])->name('meetings.index');
    Route::get('/meetings/{meeting}', [MeetingController::class, 'show'])->name('meetings.show');
    Route::get('/minutes', [MeetingController::class, 'minutes'])->name('minutes.index');
    Route::get('/files', [AssemblyFileController::class, 'index'])->name('files.index');

    // الملف يُبث من الخادم، فحد لكل مسار ببادئته
    Route::middleware(['signed', 'throttle:60,1,assembly-download'])->group(function () {
        Route::get('/meetings/{meeting}/attachments/{index}', [MeetingController::class, 'attachment'])->whereNumber('index')->name('meetings.attachment');
        Route::get('/meetings/{meeting}/minutes', [MeetingController::class, 'minutesFile'])->name('meetings.minutes');
        Route::get('/files/{file}', [AssemblyFileController::class, 'download'])->name('files.download');
    });
});
