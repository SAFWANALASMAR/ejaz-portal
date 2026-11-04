<?php

use App\Http\Controllers\Surveys\SurveyController;
use Illuminate\Support\Facades\Route;

/*
| قياس الرضا (docs/plans/surveys-stats.md). تُحمَّل من routes/web.php.
*/

// لكل مسار بادئة في حده: الحد بلا بادئة مفتاحه المستخدم (أو العنوان) وحده فتشترك فيه المسارات، فيستهلك
// فتح الصفحة حد الإجابة
// الاستبيان العام المفتوح (بطاقة «قياس الرضا»)، وبلا استبيان مفتوح صفحة برسالة أن لا استبيان الآن (SurveyController::index)
Route::get('/surveys', [SurveyController::class, 'index'])->middleware('throttle:60,1,survey-index')->name('surveys.index');

Route::where(['token' => '[A-Za-z0-9]{32}'])->group(function () {
    Route::get('/surveys/{token}', [SurveyController::class, 'show'])->middleware('throttle:60,1,survey-show')->name('surveys.show');
    Route::post('/surveys/{token}', [SurveyController::class, 'submit'])->middleware('throttle:10,1,survey-submit')->name('surveys.submit');
});
