<?php

namespace App\Actions\Competitions;

use App\Enums\QuestionType;
use App\Enums\RoleName;
use App\Models\Competition;
use App\Models\CompetitionCriterion;
use App\Models\CompetitionQuestion;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator as ValidatorInstance;

/**
 * حفظ مسابقة بمعاييرها وأسئلتها: الصلاحية، ثم فحص المدخلات كلها، ثم الحفظ في معاملة واحدة.
 * يُفحص هنا لا في النموذج وحده، فيسري من أي مكان استُدعي منه.
 *
 * المدخل بشكل نموذج اللوحة: criteria [{id?, name, weight, max_score}]، وquestions
 * [{id?, type, prompt, options [{label, is_correct}], points, is_required, max_video_seconds,
 * max_video_mb}]. والمعرّف لا يُعتمد إلا لمعيار أو سؤال من المسابقة نفسها.
 */
class SaveCompetition
{
    /**
     * مفاتيح خيارات الاختيار من متعدد بترتيبها.
     */
    private const OPTION_KEYS = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j'];

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, Competition $competition, array $data): Competition
    {
        $allowed = $competition->exists ? $actor->can('update', $competition) : $actor->can('create', Competition::class);

        if (! $allowed) {
            throw new AuthorizationException;
        }

        // بعد أول مشاركة لا يصل بنك الأسئلة من اللوحة (حقله معطل)، ولا يُقبل من غيرها
        if (array_key_exists('questions', $data) && $competition->questionsLocked()) {
            throw ValidationException::withMessages(['questions' => __('competitions.validation.questions_locked')]);
        }

        if (array_key_exists('criteria', $data) && $competition->criteriaLocked()) {
            throw ValidationException::withMessages(['criteria' => __('competitions.validation.criteria_locked')]);
        }

        $data = $this->validate($competition, $data);

        // ما لم يصل من المعايير أو الأسئلة يبقى كما هو
        $criteria = $data['criteria'] ?? null;
        $questions = $data['questions'] ?? null;
        $reviewers = $data['reviewers'] ?? null;
        unset($data['criteria'], $data['questions'], $data['reviewers']);

        if ($reviewers !== null) {
            $this->checkReviewers($competition, array_map('intval', $reviewers));
        }

        $data['categories'] = array_values(array_unique(array_filter(array_map('trim', $data['categories'] ?? []), fn (string $category): bool => $category !== '')));

        DB::transaction(function () use ($competition, $data, $criteria, $questions, $reviewers): void {
            // حفظان متزامنان للمسابقة نفسها: الثاني ينتظر، فلا يحذف ما أضافه الأول
            if ($competition->exists) {
                Competition::query()->whereKey($competition->id)->lockForUpdate()->first();
            }

            $competition->fill($data)->save();
            if ($criteria !== null) {
                $this->syncCriteria($competition, $criteria);
            }

            if ($questions !== null) {
                $this->syncQuestions($competition, $questions);
            }

            if ($reviewers !== null) {
                $competition->reviewers()->sync(array_map('intval', $reviewers));
            }
        });

        return $competition->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function validate(Competition $competition, array $data): array
    {
        $validator = Validator::make($data, [
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('competitions', 'slug')->ignore($competition->id)],
            'description' => ['nullable', 'string', 'max:5000'],
            'terms' => ['nullable', 'string', 'max:5000'],
            'prizes' => ['nullable', 'string', 'max:2000'],
            'categories' => ['nullable', 'array', 'max:20'],
            'categories.*' => ['string', 'max:60'],
            'opens_at' => ['required', 'date'],
            'closes_at' => ['required', 'date', 'after:opens_at'],
            'shuffle_questions' => ['boolean'],
            'shuffle_options' => ['boolean'],
            'is_published' => ['boolean'],

            'reviewers' => ['nullable', 'array', 'max:200'],
            'reviewers.*' => ['integer', 'distinct'],

            'criteria' => ['nullable', 'array', 'max:20'],
            'criteria.*.id' => ['nullable', 'integer', 'distinct'],
            'criteria.*.name' => ['required', 'string', 'max:150'],
            'criteria.*.weight' => ['required', 'integer', 'min:1', 'max:100'],
            'criteria.*.max_score' => ['required', 'integer', 'min:1', 'max:1000'],

            'questions' => ['nullable', 'array', 'max:200'],
            'questions.*.id' => ['nullable', 'integer', 'distinct'],
            'questions.*.type' => ['required', Rule::enum(QuestionType::class)],
            'questions.*.prompt' => ['required', 'string', 'max:2000'],
            'questions.*.is_required' => ['boolean'],
            'questions.*.options' => ['nullable', 'array', 'max:'.count(self::OPTION_KEYS)],
            'questions.*.options.*.label' => ['required', 'string', 'max:300'],
            'questions.*.options.*.is_correct' => ['boolean'],
            'questions.*.points' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'questions.*.max_video_seconds' => ['nullable', 'integer', 'min:1', 'max:'.config('competitions.video.max_seconds')],
            'questions.*.max_video_mb' => ['nullable', 'integer', 'min:1', 'max:'.config('competitions.video.max_mb')],
        ], attributes: __('competitions.attributes'));

        // الفحوص المركبة بعد سلامة كل حقل، فلا يتكرر الخطأ نفسه
        $validator->after(function (ValidatorInstance $validator) use ($competition, $data): void {
            if ($validator->errors()->isEmpty()) {
                $this->checkCriteriaAndQuestions($validator, $competition, $data);
            }
        });

        return $validator->validate();
    }

    /**
     * أوزان المعايير مجموعها 100، ولكل سؤال اختيار خياران فأكثر وخيار صحيح واحد، ولكل سؤال فيديو
     * حداه، ولا تُنشر مسابقة بلا أسئلة.
     *
     * @param  array<string, mixed>  $data
     */
    private function checkCriteriaAndQuestions(ValidatorInstance $validator, Competition $competition, array $data): void
    {
        $criteria = is_array($data['criteria'] ?? null) ? $data['criteria'] : [];
        $questions = is_array($data['questions'] ?? null) ? $data['questions'] : [];

        if ($criteria !== [] && array_sum(array_map(fn ($criterion): int => (int) ($criterion['weight'] ?? 0), $criteria)) !== 100) {
            $validator->errors()->add('criteria', __('competitions.validation.weights_total'));
        }

        foreach (array_values($questions) as $index => $question) {
            $number = $index + 1;
            $type = self::type($question['type'] ?? null);

            if ($type === QuestionType::MultipleChoice) {
                $options = is_array($question['options'] ?? null) ? $question['options'] : [];
                $correct = count(array_filter($options, fn ($option): bool => (bool) ($option['is_correct'] ?? false)));

                if (count($options) < 2 || $correct !== 1) {
                    $validator->errors()->add('questions', __('competitions.validation.choices', ['number' => $number]));
                }

                // سؤال يُصحَّح آلياً ولا درجة له لا معنى له
                if ((int) ($question['points'] ?? 0) < 1) {
                    $validator->errors()->add('questions', __('competitions.validation.points', ['number' => $number]));
                }
            }

            if ($type === QuestionType::Video && (blank($question['max_video_seconds'] ?? null) || blank($question['max_video_mb'] ?? null))) {
                $validator->errors()->add('questions', __('competitions.validation.video_limits', ['number' => $number]));
            }
        }

        $hasQuestions = array_key_exists('questions', $data) ? $questions !== [] : $competition->exists && $competition->questions()->exists();

        if (($data['is_published'] ?? false) && ! $hasQuestions) {
            $validator->errors()->add('is_published', __('competitions.validation.needs_questions'));
        }
    }

    /**
     * المحكّم المضاف حساب نشط بدور reviewer. ولا يُرفع من المسابقة محكّم أُسندت إليه مشاركات فيها.
     *
     * @param  list<int>  $ids
     *
     * @throws ValidationException
     */
    private function checkReviewers(Competition $competition, array $ids): void
    {
        // الشرط على المضافين فقط، فمحكّم مرتبط عُطّل حسابه لا يمنع حفظ المسابقة
        $current = $competition->exists ? $competition->reviewers()->pluck('users.id')->all() : [];
        $added = array_values(array_diff($ids, $current));
        $valid = User::query()->whereKey($added)->where('is_active', true)->role(RoleName::Reviewer->value)->count();

        if ($valid !== count($added)) {
            throw ValidationException::withMessages(['reviewers' => __('competitions.validation.reviewers')]);
        }

        $assigned = $competition->exists
            ? $competition->reviews()->whereNotIn('submission_reviews.reviewer_id', $ids)->exists()
            : false;

        if ($assigned) {
            throw ValidationException::withMessages(['reviewers' => __('competitions.validation.reviewer_assigned')]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function syncCriteria(Competition $competition, array $rows): void
    {
        $kept = [];

        foreach (array_values($rows) as $index => $row) {
            $criterion = $this->existing($competition->criteria(), $row['id'] ?? null) ?? new CompetitionCriterion;
            $criterion->fill([
                'name' => $row['name'],
                'weight' => $row['weight'],
                'max_score' => $row['max_score'],
                'sort_order' => $index + 1,
            ]);
            $competition->criteria()->save($criterion);
            $kept[] = $criterion->id;
        }

        $competition->criteria()->whereKeyNot($kept)->get()->each->delete();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function syncQuestions(Competition $competition, array $rows): void
    {
        $kept = [];

        foreach (array_values($rows) as $index => $row) {
            $type = self::type($row['type']) ?? throw new \LogicException('Validated question type');
            $question = $this->existing($competition->questions(), $row['id'] ?? null) ?? new CompetitionQuestion;
            $options = $type === QuestionType::MultipleChoice ? array_values($row['options'] ?? []) : [];

            $question->fill([
                'type' => $type,
                'prompt' => $row['prompt'],
                'is_required' => (bool) ($row['is_required'] ?? true),
                'options' => $options === [] ? null : array_map(fn (array $option, int $position): array => ['key' => self::OPTION_KEYS[$position], 'label' => trim($option['label'])], $options, array_keys($options)),
                'correct_option' => $this->correctKey($options),
                'points' => $type === QuestionType::MultipleChoice ? (int) ($row['points'] ?? 0) : 0,
                'max_video_seconds' => $type === QuestionType::Video ? $row['max_video_seconds'] : null,
                'max_video_mb' => $type === QuestionType::Video ? $row['max_video_mb'] : null,
                'sort_order' => $index + 1,
            ]);
            $competition->questions()->save($question);
            $kept[] = $question->id;
        }

        $competition->questions()->whereKeyNot($kept)->get()->each->delete();
    }

    /**
     * النوع من اللوحة حالة من التعداد، ومن غيرها نص.
     */
    private static function type(mixed $value): ?QuestionType
    {
        return $value instanceof QuestionType ? $value : QuestionType::tryFrom(is_string($value) ? $value : '');
    }

    /**
     * @param  list<array<string, mixed>>  $options
     */
    private function correctKey(array $options): ?string
    {
        foreach ($options as $position => $option) {
            if ($option['is_correct'] ?? false) {
                return self::OPTION_KEYS[$position];
            }
        }

        return null;
    }

    /**
     * سجل المسابقة نفسها بمعرّفه، أو null لسجل جديد أو معرّف من غيرها.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  HasMany<TModel, Competition>  $relation
     * @return TModel|null
     */
    private function existing($relation, mixed $id)
    {
        return is_numeric($id) && $relation->getParent()->exists ? $relation->clone()->whereKey((int) $id)->first() : null;
    }
}
