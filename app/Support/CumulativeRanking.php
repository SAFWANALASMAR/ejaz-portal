<?php

namespace App\Support;

use App\Enums\AttemptStatus;
use App\Enums\RankingScope;
use App\Enums\StudentGender;
use App\Models\Competition;
use App\Models\CompetitionRound;
use App\Models\CompetitionSubmission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * الترتيب التراكمي في مسابقة الطلاب (مطوية «البراهين العلمية»): مجموع درجات الطالب في الفروع (المراحل) كلها، ولا
 * يدخل الترتيب إلا من سلّم في كل الفروع، ويبقى لغيره ما شارك فيه. والترتيب لكل جنس، ولكل فئة أيضاً إن كان نطاق
 * المسابقة «لكل فئة» (RankingScope). والمتساوون في المجموع يتساوون في الترتيب (RANK)، والسحب بينهم على حد المراكز
 * (RunCumulativeTieDraw). وترتيب كل فرع باقٍ في «النتائج».
 */
final class CumulativeRanking
{
    /**
     * تسجيلات المسابقة بمجموعها (cumulative_total والدرجة القصوى cumulative_max) وعدد فروعها المسلَّمة (rounds_done)،
     * وهل شاركت في كل الفروع (eligible)، وترتيبها (cumulative_rank). على استعلام تسجيلات المسابقة (جدول اللوحة).
     *
     * @param  Builder<CompetitionSubmission>  $query
     * @return Builder<CompetitionSubmission>
     */
    public static function apply(Builder $query, Competition $competition): Builder
    {
        return $query->fromSub(self::inner($competition), 'competition_submissions');
    }

    /**
     * المشاركون في كل الفروع من جنس (وفئة إن كان النطاق لكل فئة) بمجموعهم تنازلياً، برقم تسجيلهم المرجعي، للسحب.
     *
     * @return Collection<int, array{reference: string, total: float, max: float}>
     */
    public static function ranked(Competition $competition, StudentGender $gender, ?int $categoryId): Collection
    {
        return self::apply(CompetitionSubmission::query(), $competition)
            ->where('eligible', true)
            ->where('gender', $gender->value)
            ->whereNotNull('reference')
            ->when($categoryId !== null, fn (Builder $query) => $query->where('category_id', $categoryId))
            ->orderByDesc('cumulative_total')
            ->get(['reference', 'cumulative_total', 'cumulative_max'])
            ->map(fn (CompetitionSubmission $submission): array => [
                'reference' => (string) $submission->reference,
                'total' => round((float) $submission->getAttribute('cumulative_total'), 2),
                'max' => round((float) $submission->getAttribute('cumulative_max'), 2),
            ])
            ->values();
    }

    private static function inner(Competition $competition): QueryBuilder
    {
        $rounds = (int) $competition->rounds()->count();

        $totals = DB::table('competition_attempts')
            ->whereIn('round_id', CompetitionRound::query()->where('competition_id', $competition->id)->select('id'))
            ->where('status', AttemptStatus::Submitted->value)
            ->groupBy('submission_id')
            ->select('submission_id')
            ->selectRaw('SUM(COALESCE(score, 0) + COALESCE(essay_score, 0)) AS total, SUM(max_score) AS max_total, COUNT(*) AS rounds_done');

        // عدد صحيح من القاعدة، فلا مدخل من المستخدم في التعبير
        $eligible = "(COALESCE(t.rounds_done, 0) = {$rounds} AND {$rounds} > 0)";
        $partition = 's.gender'.($competition->ranking_scope === RankingScope::Category ? ', s.category_id' : '');

        return DB::table('competition_submissions as s')
            ->leftJoinSub($totals, 't', 't.submission_id', '=', 's.id')
            ->where('s.competition_id', $competition->id)
            ->whereNotNull('s.registered_at')
            ->whereNull('s.deleted_at')
            ->select('s.*')
            ->selectRaw('COALESCE(t.total, 0) AS cumulative_total, COALESCE(t.max_total, 0) AS cumulative_max, COALESCE(t.rounds_done, 0) AS rounds_done')
            ->selectRaw("{$eligible} AS eligible")
            ->selectRaw("CASE WHEN {$eligible} THEN RANK() OVER (PARTITION BY {$eligible}, {$partition} ORDER BY COALESCE(t.total, 0) DESC) END AS cumulative_rank");
    }
}
