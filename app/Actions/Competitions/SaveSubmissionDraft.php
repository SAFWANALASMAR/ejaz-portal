<?php

namespace App\Actions\Competitions;

use App\Enums\QuestionType;
use App\Enums\SaudiRegion;
use App\Models\CompetitionQuestion;
use App\Models\CompetitionSubmission;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * حفظ مسودة المشاركة (يدوياً أو تلقائياً): مشاركة المتقدم نفسه، مسودة، والمسابقة مفتوحة. والإجابات
 * لأسئلة المسابقة النصية والاختيارية وحدها، ومفتاح الخيار من خيارات سؤاله. وما لم يصل يبقى كما هو.
 */
class SaveSubmissionDraft
{
    public const MAX_TEXT = 5000;

    /**
     * @param  array<string, mixed>  $data  {category?, region?, answers?: {معرّف السؤال: قيمة}}
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $user, CompetitionSubmission $submission, array $data): CompetitionSubmission
    {
        if (! $user->can('update', $submission)) {
            throw new AuthorizationException;
        }

        $competition = $submission->competition;

        if (! $competition->isOpen()) {
            throw new AuthorizationException(__('competitions.entry.closed'));
        }

        $questions = $competition->questions->reject(fn (CompetitionQuestion $question): bool => $question->type === QuestionType::Video)->keyBy('id');

        $rules = [
            'category' => ['nullable', 'string', Rule::in($competition->categoryList())],
            'region' => ['nullable', Rule::enum(SaudiRegion::class)],
            'answers' => ['nullable', 'array'],
        ];

        foreach ($questions as $id => $question) {
            $rules['answers.'.$id] = $question->type === QuestionType::MultipleChoice
                ? ['nullable', 'string', Rule::in($question->optionKeys())]
                : ['nullable', 'string', 'max:'.self::MAX_TEXT];
        }

        $validated = Validator::make($data, $rules, attributes: ['category' => __('competitions.entry.category'), 'region' => __('competitions.entry.region'), 'answers.*' => __('competitions.entry.answer')])->validate();

        $answers = (array) $submission->answers;

        foreach ($questions as $id => $question) {
            if (array_key_exists((string) $id, (array) ($validated['answers'] ?? []))) {
                $value = trim((string) $validated['answers'][(string) $id]);
                $answers[(string) $id] = $value === '' ? null : $value;
            }
        }

        if (array_key_exists('category', $validated)) {
            $submission->category = $validated['category'];
        }

        if (array_key_exists('region', $validated)) {
            $submission->region = $validated['region'] === null ? null : SaudiRegion::from($validated['region']);
        }

        $submission->answers = array_filter($answers, fn ($value): bool => $value !== null);

        DB::transaction(function () use ($submission): void {
            // حفظ تلقائي يصل بعد إرسال متزامن لا يكتب فوق مشاركة مرسلة
            $locked = CompetitionSubmission::query()->whereKey($submission->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isDraft()) {
                throw new AuthorizationException;
            }

            $submission->save();
        });

        return $submission;
    }
}
