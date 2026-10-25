<?php

namespace App\Actions\Competitions;

use App\Enums\QuestionType;
use App\Models\CompetitionCategory;
use App\Models\CompetitionPassage;
use App\Models\CompetitionQuestion;
use App\Models\CompetitionRound;
use App\Models\User;
use App\Support\CompetitionAccess;
use App\Support\RoundQuestions;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator as ValidatorInstance;

/**
 * حفظ سؤال في بنك مرحلة من مسابقة الطلاب (المدير يضيف الأسئلة من اللوحة، ولا يخترعها النظام):
 * - الاختيار من متعدد بنص أو مقطع أو بلاهما: خياران إلى ستة، وخيار صحيح واحد.
 * - صح وخطأ: خياراه ثابتان، والصحيح أحدهما.
 * - المقالي: حين يُفعَّل وحده، ويُصحَّح يدوياً.
 * والسؤال على نص أو مقطع يلزمه نص أو مقطع من نوعه في المرحلة نفسها. والبنك مقفل بعد أول محاولة في المرحلة.
 */
class SaveStudentQuestion
{
    public const OPTION_KEYS = ['a', 'b', 'c', 'd', 'e', 'f'];

    public const TRUE_FALSE_KEYS = ['t', 'f'];

    /**
     * @param  array<string, mixed>  $data  {type, prompt, category_id?, passage_id?, points, sort_order?, options: [{label, is_correct}], true_false?: t|f}
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, CompetitionRound $round, ?CompetitionQuestion $question, array $data): CompetitionQuestion
    {
        $competition = $round->competition;

        if (! CompetitionAccess::allows($actor, 'competitions.update') || ! $competition->student_mode || ($question !== null && $question->round_id !== $round->id)) {
            throw new AuthorizationException;
        }

        $validator = Validator::make($data, [
            'type' => ['required', Rule::enum(QuestionType::class)->only(QuestionType::student())],
            'prompt' => ['required', 'string', 'max:2000'],
            'category_id' => ['nullable', 'integer', Rule::exists(CompetitionCategory::class, 'id')],
            'passage_id' => ['nullable', 'integer', Rule::exists(CompetitionPassage::class, 'id')->where('round_id', $round->id)],
            'points' => ['required', 'integer', 'min:1', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'options' => ['nullable', 'array', 'max:'.count(self::OPTION_KEYS)],
            'options.*.label' => ['required', 'string', 'max:500'],
            'options.*.is_correct' => ['boolean'],
            'true_false' => ['nullable', Rule::in(self::TRUE_FALSE_KEYS)],
        ], attributes: __('competitions.student.question_attributes'));

        $validator->after(function (ValidatorInstance $validator) use ($data, $round): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $type = QuestionType::from((string) $data['type']);
            $options = array_values((array) ($data['options'] ?? []));

            if (in_array($type, [QuestionType::MultipleChoice, QuestionType::PassageChoice, QuestionType::VideoChoice], true)) {
                $correct = count(array_filter($options, fn ($option): bool => (bool) ($option['is_correct'] ?? false)));

                if (count($options) < 2 || $correct !== 1) {
                    $validator->errors()->add('options', __('competitions.student.choices_rule'));
                }
            }

            if ($type === QuestionType::TrueFalse && blank($data['true_false'] ?? null)) {
                $validator->errors()->add('true_false', __('competitions.student.true_false_rule'));
            }

            $kind = $type->passageKind();
            $passage = filled($data['passage_id'] ?? null) ? CompetitionPassage::query()->find((int) $data['passage_id']) : null;

            if ($kind !== null && ($passage === null || $passage->kind !== $kind)) {
                $validator->errors()->add('passage_id', __('competitions.student.passage_rule'));
            }

            // سؤال النص بفئة النص نفسها، فلا يتفرق النص عن أسئلته بين الفئات
            if ($passage !== null && $passage->category_id !== null && (int) ($data['category_id'] ?? 0) !== $passage->category_id) {
                $validator->errors()->add('category_id', __('competitions.student.passage_category_rule'));
            }

            if ($round->locked()) {
                $validator->errors()->add('prompt', __('competitions.student.bank_locked'));
            }
        });

        $validated = $validator->validate();
        $type = QuestionType::from((string) $validated['type']);

        [$options, $correct] = match (true) {
            $type === QuestionType::TrueFalse => [
                array_map(fn (string $key): array => ['key' => $key, 'label' => __('competitions.student.true_false.'.$key)], self::TRUE_FALSE_KEYS),
                (string) $validated['true_false'],
            ],
            $type === QuestionType::Essay => [null, null],
            default => $this->choices(array_values((array) ($validated['options'] ?? []))),
        };

        $saved = DB::transaction(function () use ($round, $question, $validated, $type, $options, $correct): CompetitionQuestion {
            // قفل المرحلة: أول محاولة وصلت بين الفحص والحفظ ترفضه
            $locked = CompetitionRound::query()->whereKey($round->id)->lockForUpdate()->firstOrFail();

            if ($locked->locked()) {
                throw ValidationException::withMessages(['prompt' => __('competitions.student.bank_locked')]);
            }

            $question ??= new CompetitionQuestion;
            $question->competition_id = $round->competition_id;
            $question->fill([
                'round_id' => $round->id,
                'type' => $type,
                'prompt' => trim((string) $validated['prompt']),
                'category_id' => filled($validated['category_id'] ?? null) ? (int) $validated['category_id'] : null,
                'passage_id' => $type->passageKind() !== null ? (int) $validated['passage_id'] : null,
                'points' => (int) $validated['points'],
                'is_required' => false,
                'sort_order' => (int) ($validated['sort_order'] ?? ($question->exists ? $question->sort_order : $round->questions()->max('sort_order') + 1)),
                'options' => $options,
                'correct_option' => $correct,
            ])->save();

            RoundQuestions::forget($round->id);

            return $question;
        });

        DB::afterCommit(fn () => RoundQuestions::forget($round->id));

        return $saved;
    }

    /**
     * @param  list<mixed>  $rows
     * @return array{0: list<array{key: string, label: string}>, 1: string}
     */
    private function choices(array $rows): array
    {
        $options = [];
        $correct = '';

        foreach ($rows as $index => $row) {
            $key = self::OPTION_KEYS[$index];
            $options[] = ['key' => $key, 'label' => trim((string) ($row['label'] ?? ''))];

            if ((bool) ($row['is_correct'] ?? false)) {
                $correct = $key;
            }
        }

        return [$options, $correct];
    }
}
