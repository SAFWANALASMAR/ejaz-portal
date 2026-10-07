<?php

use App\Http\Controllers\Surveys\SurveyController;
use Illuminate\Support\Facades\Route;

/*
| قياس الرضا (docs/plans/surveys-stats.md). تُحمَّل من routes/web.php.
*/

Route::where(['token' => '[A-Za-z0-9]{32}'])->group(function () {
    Route::get('/surveys/{token}', [SurveyController::class, 'show'])->middleware('throttle:60,1')->name('surveys.show');
    Route::post('/surveys/{token}', [SurveyController::class, 'submit'])->middleware('throttle:10,1')->name('surveys.submit');
});
