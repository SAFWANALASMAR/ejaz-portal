<?php

use App\Http\Controllers\AchievementController;
use Illuminate\Support\Facades\Route;

/*
| «إنجازاتنا» (docs/decisions/2026-11-02-achievements.md). تُحمَّل من routes/web.php.
| الصفحة العامة للمنشور وحده، والمرفق برابط مؤقت موقّع يُفحص عنده النشر أو الصلاحية، بلا حفظ عند وسيط مشترك.
*/

Route::get('/achievements', [AchievementController::class, 'index'])->name('achievements.index');

Route::get('/achievements/{achievement}/attachment', [AchievementController::class, 'attachment'])
    ->middleware(['signed', 'throttle:60,1,achievements-attachment', 'cache.headers:private;no_store'])
    ->name('achievements.attachment');
