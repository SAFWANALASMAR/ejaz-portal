<?php

use App\Http\Controllers\Account\CompetitionsController;
use App\Http\Controllers\Competitions\CompetitionController;
use App\Http\Controllers\Competitions\EntryController;
use App\Http\Controllers\Competitions\ReviewVideoController;
use App\Http\Controllers\Competitions\VideoUploadController;
use Illuminate\Support\Facades\Route;

/*
| المسابقات (المرحلة 2 — docs/plans/competitions.md). تُحمَّل من routes/web.php.
*/

$slug = '[a-z0-9]+(?:-[a-z0-9]+)*';

Route::get('/competitions', [CompetitionController::class, 'index'])->name('competitions.index');
Route::get('/competitions/{slug}', [CompetitionController::class, 'show'])->where('slug', $slug)->name('competitions.show');

// التقديم: حساب مسجل، والمشاركة مشاركته وحدها (EntryController والإجراءات)
Route::middleware('auth')->where(['slug' => $slug])->group(function () {
    // المسودة تُنشأ بـ POST وحده وبحد معدل، فلا ينشئ تكرار فتح الرابط مسودات
    Route::post('/competitions/{slug}/entry/start', [EntryController::class, 'start'])->middleware('throttle:10,1,competition-entry-start')->name('competitions.entry.start');
    Route::get('/competitions/{slug}/entry', [EntryController::class, 'show'])->middleware('throttle:60,1,competition-entry')->name('competitions.entry');
    Route::post('/competitions/{slug}/entry', [EntryController::class, 'save'])->middleware('throttle:30,1')->name('competitions.entry.save');
    Route::post('/competitions/{slug}/entry/autosave', [EntryController::class, 'autosave'])->middleware('throttle:60,1')->name('competitions.entry.autosave');
    Route::post('/competitions/{slug}/entry/submit', [EntryController::class, 'submit'])->middleware('throttle:10,1')->name('competitions.entry.submit');

    // رفع المقطع مجزأً: الأجزاء بروابط موقّعة مؤقتة، وكثيرة لكل مقطع فحدها أعلى
    Route::post('/competitions/{slug}/entry/videos/{question}', [VideoUploadController::class, 'start'])->whereNumber('question')->middleware('throttle:20,1')->name('competitions.videos.start');
    Route::get('/competition-videos/{video}', [VideoUploadController::class, 'status'])->middleware('throttle:60,1')->name('competitions.videos.status');
    Route::put('/competition-videos/{video}/chunks/{index}', [VideoUploadController::class, 'chunk'])->whereNumber('index')->middleware(['signed', 'throttle:600,1'])->name('competitions.videos.chunk');
    Route::post('/competition-videos/{video}/complete', [VideoUploadController::class, 'complete'])->middleware('throttle:20,1')->name('competitions.videos.complete');

    Route::get('/account/competitions', CompetitionsController::class)->name('account.competitions');

    // مقطع المشاركة للمحكّم المسند إليه: يُفحص الإسناد عند كل طلب. والمشغّل يطلب أجزاءً عند التقديم
    Route::get('/competition-reviews/{review}/videos/{video}', ReviewVideoController::class)->middleware('throttle:240,1')->name('competitions.reviews.video');
});
