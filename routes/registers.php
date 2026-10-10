<?php

use App\Http\Controllers\Admin\RegisterAttachmentController;
use Illuminate\Support\Facades\Route;

/*
| السجلات الداخلية (docs/plans/registers.md). تُحمَّل من routes/web.php.
| تنزيل مرفق المستند برابط مؤقت موقّع يصدره إجراء اللوحة، ويُفحص الدخول والصلاحية عند الطلب ويُسجَّل.
*/

Route::middleware(['auth', 'signed', 'throttle:60,1,registers-download', 'cache.headers:private;no_store'])
    ->get('/admin-files/registers/{entry}/{index}', RegisterAttachmentController::class)
    ->whereNumber('index')
    ->name('registers.attachment');
