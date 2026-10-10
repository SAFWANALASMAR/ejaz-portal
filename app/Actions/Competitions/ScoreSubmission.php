<?php

namespace App\Actions\Competitions;

use App\Models\Competition;
use App\Models\CompetitionCriterion;
use App\Models\SubmissionReview;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * إدخال المحكّم درجاته: لكل معيار من معايير المسابقة درجة بين صفر ودرجته العظمى، والمجموع الموزون
 * من 100 = Σ (الدرجة ÷ العظمى × الوزن). والإنهاء يشترط درجات كل المعايير. ولا إدخال بعد الاعتماد.
 */
class ScoreSubmission
{
    /**
     * @param  array<int|string, mixed>  $scores  {معرّف المعيار: الدرجة}
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $reviewer, SubmissionReview $review, array $scores, bool $complete): SubmissionReview
    {
        if (! $reviewer->can('update', $review)) {
            throw new AuthorizationException;
        }

        $criteria = $review->loadMissing('submission.competition.criteria')->submission->competition->criteria;

        $rules = [];

        foreach ($criteria as $criterion) {
            $rules[(string) $criterion->id] = [$complete ? 'required' : 'nullable', 'integer', 'min:0', 'max:'.$criterion->max_score];
        }

        $validated = Validator::make(array_map(fn ($value) => $value === '' ? null : $value, $scores), $rules, attributes: $criteria->mapWithKeys(fn (CompetitionCriterion $criterion): array => [(string) $criterion->id => $criterion->name])->all())->validate();

        $clean = array_map('intval', array_filter($validated, fn ($value): bool => $value !== null));

        $review->scores = $clean;
        $review->total = count($clean) === $criteria->count() && $criteria->isNotEmpty()
            ? round($criteria->sum(fn (CompetitionCriterion $criterion): float => $clean[(string) $criterion->id] / $criterion->max_score * $criterion->weight), 2)
            : null;
        $review->completed_at = $complete ? ($review->completed_at ?? now()) : null;

        DB::transaction(function () use ($review): void {
            // القفل نفسه الذي يأخذه الاعتماد: درجة تصل بعده تُرفض ولو مرّ فحص السياسة قبله
            $competition = Competition::query()->whereKey($review->submission->competition_id)->lockForUpdate()->firstOrFail();

            if ($competition->resultsApproved()) {
                throw new AuthorizationException;
            }

            $review->save();
        });

        return $review;
    }
}
