<?php

namespace App\Actions\Surveys;

use App\Enums\ActivityEvent;
use App\Enums\SurveyQuestionType;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Support\SurveyResults;
use Illuminate\Support\Facades\DB;

/**
 * حذف ما قد يدل على صاحب إجابة استبيان حُذف حسابه، بعد مدة الاحتفاظ (مراجعة #5): تُفرَّغ الإجابات
 * النصية، وتُفصل الإجابة عن الحساب. والإجابات الرقمية (المقياس والاختيار) تبقى للإحصاء.
 * يستدعيه ejaz:purge-anonymized-data وحده، ولا تراجع عنه.
 */
class PurgeSurveyResponsePersonalData
{
    public function handle(SurveyResponse $response): void
    {
        DB::transaction(function () use ($response): void {
            /** @var SurveyResponse $locked */
            $locked = SurveyResponse::query()->whereKey($response->id)->lockForUpdate()->firstOrFail();

            $texts = $locked->survey->questions
                ->filter(fn (SurveyQuestion $question): bool => $question->type === SurveyQuestionType::Text)
                ->map(fn (SurveyQuestion $question): string => (string) $question->id)
                ->all();

            $locked->answers = array_diff_key((array) $locked->answers, array_flip($texts));
            $locked->user_id = null;
            $locked->save();

            activity('surveys')->performedOn($locked)
                ->event(ActivityEvent::PersonalDataPurged->value)
                ->withProperties(['survey_id' => $locked->survey_id])
                ->log(ActivityEvent::PersonalDataPurged->value);

            SurveyResults::forget($locked->survey);
        });
    }
}
