<?php

use App\Http\Controllers\JobFileDocumentController;
use App\Http\Middleware\AdminIdleTimeout;
use App\Http\Middleware\EnsureTwoFactorIsEnabled;
use Illuminate\Support\Facades\Route;

/*
| ملفات الوظائف (docs/plans/job-files.md). الشاشات في لوحة الإدارة، وتنزيل المستند برابط مؤقت موقّع يُفحص
| عنده الدخول والتحقق الثنائي والصلاحية من جديد، ويُسجَّل. تُحمَّل من routes/web.php.
*/

Route::middleware(['auth', EnsureTwoFactorIsEnabled::class, AdminIdleTimeout::class, 'signed', 'throttle:60,1,job-files-download', 'cache.headers:private;no_store'])
    ->get('/job-files/documents/{document}/download', [JobFileDocumentController::class, 'download'])
    ->name('job-files.documents.download');
