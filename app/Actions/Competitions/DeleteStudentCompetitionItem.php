<?php

namespace App\Actions\Competitions;

use App\Models\CompetitionField;
use App\Models\CompetitionPassage;
use App\Models\CompetitionQuestion;
use App\Models\CompetitionRound;
use App\Models\User;
use App\Support\CompetitionAccess;
use App\Support\RoundQuestions;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * حذف مرحلة أو سؤال أو نص أو حقل من مسابقة الطلاب. والمرحلة وأسئلتها ونصوصها لا تُحذف بعد أول محاولة فيها
 * (إجابات الطلاب وترتيبهم مربوطة بها). والحقل يُحذف في أي وقت، وقيمه المسجلة تبقى في التسجيلات.
 */
class DeleteStudentCompetitionItem
{
    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, CompetitionRound|CompetitionQuestion|CompetitionPassage|CompetitionField $item): void
    {
        $competition = $item instanceof CompetitionRound || $item instanceof CompetitionField
            ? $item->competition
            : $item->round->competition;

        if (! CompetitionAccess::allows($actor, 'competitions.update') || ! $competition->student_mode) {
            throw new AuthorizationException;
        }

        if ($item instanceof CompetitionField) {
            $item->delete();

            return;
        }

        $roundId = $item instanceof CompetitionRound ? $item->id : (int) $item->round_id;

        DB::transaction(function () use ($item, $roundId): void {
            $round = CompetitionRound::query()->whereKey($roundId)->lockForUpdate()->firstOrFail();

            if ($round->locked()) {
                throw ValidationException::withMessages(['round' => __('competitions.student.bank_locked')]);
            }

            if ($item instanceof CompetitionRound) {
                $item->questions()->delete();
                $item->passages()->delete();
            }

            $item->delete();
        });

        DB::afterCommit(fn () => RoundQuestions::forget($roundId));
    }
}
