<?php

namespace App\Filament\Resources\Surveys\Pages;

use App\Actions\Surveys\SaveSurvey;
use App\Filament\Concerns\SavesThroughAction;
use App\Models\Survey;
use App\Models\User;

/**
 * صفحتا إنشاء الاستبيان وتعديله تحفظان عبر SaveSurvey (SavesThroughAction).
 */
trait SavesSurveyThroughAction
{
    use SavesThroughAction;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function saveSurvey(Survey $survey, array $data): Survey
    {
        /** @var User $actor */
        $actor = auth()->user();

        return $this->saveThroughAction($survey, [], fn (): Survey => app(SaveSurvey::class)->handle($actor, $survey, $data));
    }
}
