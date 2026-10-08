<?php

namespace App\Actions\Surveys;

use App\Enums\RoleName;
use App\Enums\SubmissionStatus;
use App\Enums\SurveyAudience;
use App\Enums\SurveyQuestionType;
use App\Models\Competition;
use App\Models\CompetitionSubmission;
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
 * إجابة استبيان: منشور مفتوح، ولغير المسجل إن سمح به، ولفئته المستهدفة وحدها، والإجابات بأنواع
 * أسئلتها، والإلزامي. وإجابة واحدة لكل صاحب حساب بقيد فريد، فالمكرر يُرفض ولو تزامن طلبان.
 */
class SubmitSurveyResponse
{
    public const MAX_TEXT = 2000;

    /**
     * حد النص الواحد في الاستبيان المفتوح لغير المسجلين (مراجعة #5).
     */
    public const MAX_GUEST_SURVEY_TEXT = 1000;

    public static function maxText(Survey $survey): int
    {
        return $survey->allow_guests ? self::MAX_GUEST_SURVEY_TEXT : self::MAX_TEXT;
    }

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

        if (! self::eligible($survey, $user)) {
            throw new AuthorizationException(__('surveys.public.not_audience'));
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
                SurveyQuestionType::Text => [$required, 'string', 'max:'.self::maxText($survey), 'not_regex:/\x00/'],
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
            DB::transaction(function () use ($response, $survey, $user): void {
                // سقف إجابات غير المسجلين على صف الاستبيان المقفول، فلا يتجاوزه ضيفان متزامنان. وبلا سقف
                // مضبوط لا يجيب غير مسجل (SaveSurvey يلزمه متى سُمح لهم)
                if ($user === null) {
                    $locked = Survey::query()->whereKey($survey->id)->lockForUpdate()->firstOrFail();

                    if ($locked->guest_limit === null || $locked->guest_responses >= $locked->guest_limit) {
                        throw new AuthorizationException(__('surveys.public.guests_full'));
                    }

                    // بلا سجل عمليات لكل إجابة: عدّاد لا تعديل إداري
                    Survey::query()->whereKey($survey->id)->increment('guest_responses');
                }

                $response->save();
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['answers' => __('surveys.public.already')]);
        }

        SurveyResults::forget($survey);

        return $response;
    }

    /**
     * الفئة المستهدفة: استبيان المسابقة لمن أرسل مشاركة فيها، واستبيان «الأعضاء» لعضو الجمعية
     * العمومية. وفي الحالتين لا يجيب غير المسجل ولو سُمح لغير المسجلين. وما سواهما لكل من يصل إليه.
     */
    public static function eligible(Survey $survey, ?User $user): bool
    {
        if ($survey->subject_type === (new Competition)->getMorphClass()) {
            return $user !== null && CompetitionSubmission::query()
                ->where('competition_id', $survey->subject_id)
                ->where('user_id', $user->id)
                ->where('status', SubmissionStatus::Submitted)
                ->exists();
        }

        if ($survey->audience === SurveyAudience::Members) {
            return $user !== null && $user->hasRole(RoleName::AssemblyMember->value);
        }

        return true;
    }

    public static function answered(Survey $survey, User $user): bool
    {
        return $survey->responses()->where('user_id', $user->id)->exists();
    }
}
