<?php

namespace App\Actions\Competitions;

use App\Enums\ActivityEvent;
use App\Enums\SubmissionStatus;
use App\Models\Competition;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * اعتماد النتائج: بعد إغلاق التقديم وإنهاء كل تحكيم مسند، تُحسب الدرجة النهائية لكل مشاركة (متوسط
 * مجاميع محكّميها)، وتُقفل الدرجات فلا إدخال ولا تعديل بعده (SubmissionReviewPolicy وScoreSubmission).
 */
class ApproveResults
{
    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, Competition $competition): Competition
    {
        if (! $actor->can('competitions.approve') || $competition->resultsApproved()) {
            throw new AuthorizationException;
        }

        if ($competition->isOpen() || $competition->closes_at->isFuture()) {
            throw ValidationException::withMessages(['competition' => __('competitions.reviews.still_open')]);
        }

        if ($competition->reviews()->whereNull('completed_at')->exists()) {
            throw ValidationException::withMessages(['competition' => __('competitions.reviews.incomplete')]);
        }

        DB::transaction(function () use ($actor, $competition): void {
            Competition::query()->whereKey($competition->id)->lockForUpdate()->first();

            $competition->submissions()->where('status', SubmissionStatus::Submitted)->with('reviews')->lazyById()->each(function ($submission): void {
                $totals = $submission->reviews->pluck('total')->filter(fn ($total): bool => $total !== null);
                $submission->final_score = $totals->isEmpty() ? null : round((float) $totals->avg(), 2);
                $submission->save();
            });

            $competition->results_approved_at = now();
            $competition->results_approved_by = $actor->id;
            $competition->save();
        });

        activity('competitions')->performedOn($competition)->causedBy($actor)->event(ActivityEvent::ResultsApproved->value)->log(ActivityEvent::ResultsApproved->value);

        return $competition;
    }
}
