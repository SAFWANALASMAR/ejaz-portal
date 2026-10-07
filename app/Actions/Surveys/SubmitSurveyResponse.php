<?php

namespace App\Actions\Surveys;

use App\Enums\SurveyQuestionType;
use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Support\SurveyResults;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * إجابة استبيان: منشور مفتوح، ولغير المسجل إن سمح به، والإجابات بأنواع أسئلتها، والإلزامي. وإجابة
 * واحدة لكل صاحب حساب بقيد فريد، فالمكرر يُرفض ولو تزامن طلبان.
 */
class SubmitSurveyResponse
{
    public const MAX_TEXT = 2000;

    /**
     * @param  array<int|string, mixed>  $answers  {معرّف السؤال: قيمة}
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(?User $user, Survey $survey, array $answers): SurveyResponse
    {
        if (! $survey->isOpen() || ($user === null && ! $survey->allow_guests)) {
            throw new AuthorizationException(__('surveys.public.unavailable'));
        }

        if ($user !== null && self::answered($survey, $user)) {
            throw ValidationException::withMessages(['answers' => __('surveys.public.already')]);
        }

        $rules = [];
        $attributes = [];

        foreach ($survey->questions as $index => $question) {
            $key = 'answers.'.$question->id;
            $required = $question->is_required ? 'required' : 'nullable';
            $attributes[$key] = __('surveys.public.question', ['number' => $index + 1]);

            $rules[$key] = match ($question->type) {
                SurveyQuestionType::Scale => [$required, 'integer', 'between:1,5'],
                SurveyQuestionType::Choice => [$required, 'integer', Rule::in(array_keys($question->optionList()))],
                // البايت الصفري لا يقبله PostgreSQL في النص، فيُرفض هنا لا بخطأ خادم يكتب الإجابة في السجل
                SurveyQuestionType::Text => [$required, 'string', 'max:'.self::MAX_TEXT, 'not_regex:/\x00/'],
            };
        }

        $validated = Validator::make(['answers' => $answers], $rules, attributes: $attributes)->validate();

        $clean = [];

        foreach ($survey->questions as $question) {
            $value = $validated['answers'][$question->id] ?? null;

            if ($value !== null && $value !== '') {
                $clean[(string) $question->id] = $question->type === SurveyQuestionType::Text ? trim((string) $value) : (int) $value;
            }
        }

        $response = new SurveyResponse;
        $response->survey_id = $survey->id;
        $response->user_id = $user?->id;
        $response->answers = $clean;
        $response->submitted_at = now();

        try {
            DB::transaction(fn () => $response->save());
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['answers' => __('surveys.public.already')]);
        }

        SurveyResults::forget($survey);

        return $response;
    }

    public static function answered(Survey $survey, User $user): bool
    {
        return $survey->responses()->where('user_id', $user->id)->exists();
    }
}
