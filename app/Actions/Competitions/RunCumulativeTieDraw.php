<?php

namespace App\Actions\Competitions;

use App\Enums\ActivityEvent;
use App\Enums\RankingScope;
use App\Enums\StudentGender;
use App\Models\Competition;
use App\Models\CompetitionDraw;
use App\Models\User;
use App\Support\CompetitionAccess;
use App\Support\CumulativeRanking;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * السحب بين المتساوين في الترتيب التراكمي (مطوية «البراهين العلمية»: الجوائز لكل جنس على مجموع الفروع، والمشاركة في
 * كلها شرط): بعد نشر نتائج كل الفروع، لجنس (وفئة إن كان النطاق لكل فئة)، على حد المراكز المعلنة (عدد صفوف جدول
 * الجوائز افتراضياً). وسجل السحب الثابت نفسه بلا مرحلة (RunTieDraw::record)، وسحب نافذ واحد لكل جنس وفئة بقفل
 * صف المسابقة، ويُلغى مرة واحدة بسبب مسجّل (VoidTieDraw).
 */
class RunCumulativeTieDraw
{
    /**
     * @return array{0: CompetitionDraw, 1: bool} السجل، وهل نُفّذ الآن (لا مسجلاً من قبل)
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, Competition $competition, StudentGender $gender, ?int $categoryId, int $places): array
    {
        if (! CompetitionAccess::managesResults($actor, $competition) || ! $competition->student_mode) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($actor, $competition, $gender, $categoryId, $places): array {
            // قفل صف المسابقة يسلسل السحبين المتزامنين والإلغاء وحفظ نطاق الترتيب، والنطاق من النسخة المقفولة
            $locked = Competition::query()->whereKey($competition->id)->lockForUpdate()->firstOrFail();

            if ($locked->ranking_scope === RankingScope::Category && $categoryId === null) {
                throw ValidationException::withMessages(['category_id' => __('competitions.student.cumulative_category_required')]);
            }

            // النطاق «كل الفئات»: الترتيب واحد للجنس، فلا فئة في السحب
            if ($locked->ranking_scope !== RankingScope::Category) {
                $categoryId = null;
            }

            $existing = $this->existing($locked, $gender, $categoryId);

            if ($existing !== null) {
                if ((int) $existing->places !== $places) {
                    throw ValidationException::withMessages(['places' => __('competitions.student.cumulative_draw_other_places', ['places' => (int) $existing->places])]);
                }

                return [$existing, false];
            }

            // عند إعلان الجوائز: بعد نشر نتائج كل الفروع
            $rounds = $locked->rounds()->get();

            if ($rounds->isEmpty() || $rounds->contains(fn ($round): bool => ! $round->resultsPublished())) {
                throw ValidationException::withMessages(['places' => __('competitions.student.cumulative_before_publish')]);
            }

            $draw = RunTieDraw::record(CumulativeRanking::ranked($locked, $gender, $categoryId), $places);
            $draw->competition_id = $locked->id;
            $draw->round_id = null;
            $draw->category_id = $categoryId;
            $draw->gender = $gender;
            $draw->executed_by = $actor->id;
            $draw->save();

            if ($locked->ranking_locked_at === null) {
                $locked->forceFill(['ranking_locked_at' => now()])->save();
            }

            activity('competitions')->performedOn($draw)->causedBy($actor)
                ->event(ActivityEvent::TieDrawExecuted->value)
                ->withProperties(['cumulative' => true, 'gender' => $gender->value, 'category_id' => $categoryId, 'entrants_hash' => $draw->entrants_hash])
                ->log(ActivityEvent::TieDrawExecuted->value);

            return [$draw, true];
        });
    }

    /**
     * السحب النافذ (غير الملغى) للترتيب التراكمي لجنس وفئة.
     */
    public function existing(Competition $competition, StudentGender $gender, ?int $categoryId): ?CompetitionDraw
    {
        return CompetitionDraw::query()
            ->where('competition_id', $competition->id)
            ->whereNull('round_id')
            ->where('gender', $gender->value)
            ->when($categoryId === null, fn ($query) => $query->whereNull('category_id'), fn ($query) => $query->where('category_id', $categoryId))
            ->whereDoesntHave('void')
            ->first();
    }
}
