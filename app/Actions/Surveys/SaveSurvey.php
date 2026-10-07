<?php

namespace App\Actions\Surveys;

use App\Enums\SurveyAudience;
use App\Enums\SurveyQuestionType;
use App\Models\Competition;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator as ValidatorInstance;

/**
 * حفظ استبيان بأسئلته: الصلاحية، ثم فحص المدخلات كلها، ثم الحفظ في معاملة. والأسئلة تُقفل بعد أول
 * إجابة، لأن الإجابات مربوطة بها. المدخل بشكل نموذج اللوحة: questions [{id?, type, prompt,
 * options [{label}], is_required}]، وcompetition_id للربط بمسابقة أو null للعام.
 */
class SaveSurvey
{
    /**
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, Survey $survey, array $data): Survey
    {
        $allowed = $survey->exists ? $actor->can('update', $survey) : $actor->can('create', Survey::class);

        if (! $allowed) {
            throw new AuthorizationException;
        }

        if (array_key_exists('questions', $data) && $survey->exists && $survey->responses()->exists()) {
            throw ValidationException::withMessages(['questions' => __('surveys.validation.questions_locked')]);
        }

        $validated = $this->validate($survey, $data);
        $questions = $validated['questions'] ?? null;
        $competitionId = $validated['competition_id'] ?? null;
        unset($validated['questions'], $validated['competition_id']);

        DB::transaction(function () use ($survey, $validated, $questions, $competitionId, $data): void {
            $survey->fill($validated);

            if (array_key_exists('competition_id', $data)) {
                $survey->subject()->associate($competitionId === null ? null : Competition::query()->findOrFail($competitionId));
            }

            $survey->save();

            if ($questions !== null) {
                $this->syncQuestions($survey, $questions);
            }
        });

        return $survey->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function validate(Survey $survey, array $data): array
    {
        $validator = Validator::make($data, [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'audience' => ['required', Rule::enum(SurveyAudience::class)],
            'competition_id' => ['nullable', 'integer', Rule::exists('competitions', 'id')->whereNull('deleted_at')],
            'allow_guests' => ['boolean'],
            'opens_at' => ['nullable', 'date'],
            'closes_at' => ['nullable', 'date', 'after:opens_at'],
            'is_published' => ['boolean'],
            'questions' => ['nullable', 'array', 'max:50'],
            'questions.*.id' => ['nullable', 'integer', 'distinct'],
            'questions.*.type' => ['required', Rule::enum(SurveyQuestionType::class)],
            'questions.*.prompt' => ['required', 'string', 'max:1000'],
            'questions.*.is_required' => ['boolean'],
            'questions.*.options' => ['nullable', 'array', 'max:10'],
            'questions.*.options.*.label' => ['required', 'string', 'max:200'],
        ], attributes: __('surveys.attributes'));

        $validator->after(function (ValidatorInstance $validator) use ($survey, $data): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            foreach (array_values((array) ($data['questions'] ?? [])) as $index => $question) {
                if (self::type($question['type'] ?? null) === SurveyQuestionType::Choice && count((array) ($question['options'] ?? [])) < 2) {
                    $validator->errors()->add('questions', __('surveys.validation.choices', ['number' => $index + 1]));
                }
            }

            // ما لم تصل الأسئلة (مقفلة بعد أول إجابة) فالمحفوظة هي المعتبرة
            $hasQuestions = array_key_exists('questions', $data)
                ? $data['questions'] !== []
                : $survey->exists && $survey->questions()->exists();

            if (($data['is_published'] ?? false) && ! $hasQuestions) {
                $validator->errors()->add('is_published', __('surveys.validation.needs_questions'));
            }
        });

        return $validator->validate();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function syncQuestions(Survey $survey, array $rows): void
    {
        $kept = [];

        foreach (array_values($rows) as $index => $row) {
            $type = self::type($row['type']) ?? throw new \LogicException('Validated question type');
            $id = $row['id'] ?? null;
            $question = (is_numeric($id) ? $survey->questions()->whereKey((int) $id)->first() : null) ?? new SurveyQuestion;

            $question->fill([
                'type' => $type,
                'prompt' => $row['prompt'],
                'options' => $type === SurveyQuestionType::Choice ? array_map(fn (array $option): string => trim((string) $option['label']), array_values($row['options'] ?? [])) : null,
                'is_required' => (bool) ($row['is_required'] ?? true),
                'sort_order' => $index + 1,
            ]);
            $survey->questions()->save($question);
            $kept[] = $question->id;
        }

        $survey->questions()->whereKeyNot($kept)->delete();
    }

    private static function type(mixed $value): ?SurveyQuestionType
    {
        return $value instanceof SurveyQuestionType ? $value : SurveyQuestionType::tryFrom(is_string($value) ? $value : '');
    }
}
