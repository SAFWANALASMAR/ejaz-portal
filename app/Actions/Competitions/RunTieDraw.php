<?php

namespace App\Actions\Competitions;

use App\Enums\ActivityEvent;
use App\Enums\AttemptStatus;
use App\Models\CompetitionAttempt;
use App\Models\CompetitionDraw;
use App\Models\CompetitionRound;
use App\Models\User;
use App\Support\CompetitionAccess;
use App\Support\DrawSelector;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * السحب بين المتساوين في الدرجة عند إعلان الجوائز (قرار المدير التنفيذي): يُرتَّب المسلِّمون في المرحلة (والفئة
 * إن اختيرت) بدرجتهم، فإن تساوى طلاب على حد المراكز حُصروا وسُحب منهم بقدر المراكز الباقية بالمولّد الآمن
 * وبذرة وبصمة مسجلة في سجل السحب الثابت (RunDraw وDrawSelector). وسحب واحد لكل مرحلة وفئة بقيد فريد في
 * القاعدة: إعادة الضغط تعيد النتيجة المسجلة نفسها ولا تغيّرها، ويُتحقق منه بـ ejaz:verify-draw.
 */
class RunTieDraw
{
    /**
     * @return array{0: CompetitionDraw, 1: bool} السجل، وهل نُفّذ الآن (لا مسجلاً من قبل)
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, CompetitionRound $round, ?int $categoryId, int $places): array
    {
        if (! CompetitionAccess::allows($actor, 'competitions.draw') || ! $round->competition->student_mode) {
            throw new AuthorizationException;
        }

        $existing = $this->existing($round, $categoryId);

        // سحب واحد لكل مرحلة وفئة بمراكزه: إعادة الضغط بالعدد نفسه تعرضه، وبعدد آخر تُرفض ولا تُغيّره
        if ($existing !== null) {
            if ((int) $existing->places !== $places) {
                throw ValidationException::withMessages(['places' => __('competitions.student.draw_other_places', ['places' => (int) $existing->places])]);
            }

            return [$existing, false];
        }

        // عند إعلان الجوائز: بعد نشر نتائج المرحلة (ونشرها بعد انتهاء نافذتها)
        if (! $round->resultsPublished()) {
            throw ValidationException::withMessages(['places' => __('competitions.student.draw_before_publish')]);
        }

        $ranked = $this->ranked($round, $categoryId);

        // الترتيب بالدرجة الخام لا يصح إلا والدرجة القصوى واحدة للجميع
        if ($ranked->pluck('max')->unique()->count() > 1) {
            throw ValidationException::withMessages(['places' => __('competitions.student.mixed_max_scores')]);
        }

        if ($places < 1 || $places >= $ranked->count()) {
            throw ValidationException::withMessages(['places' => __('competitions.student.draw_places', ['max' => max(0, $ranked->count() - 1)])]);
        }

        $boundary = (float) $ranked->values()[$places - 1]['total'];
        $above = $ranked->filter(fn (array $row): bool => $row['total'] > $boundary)->count();
        /** @var list<string> $tied */
        $tied = $ranked->filter(fn (array $row): bool => $row['total'] === $boundary)->pluck('reference')->sort()->values()->all();
        $spots = $places - $above;

        if (count($tied) <= $spots) {
            throw ValidationException::withMessages(['places' => __('competitions.student.no_tie')]);
        }

        $seed = bin2hex(random_bytes(32));

        try {
            return DB::transaction(function () use ($actor, $round, $categoryId, $places, $boundary, $tied, $spots, $seed): array {
                $draw = new CompetitionDraw;
                $draw->competition_id = $round->competition_id;
                $draw->round_id = $round->id;
                $draw->category_id = $categoryId;
                $draw->places = $places;
                $draw->tie_score = $boundary;
                $draw->seed = $seed;
                $draw->entrants = $tied;
                $draw->entrants_hash = DrawSelector::fingerprint($tied);
                $draw->entrants_count = count($tied);
                $draw->winners_count = $spots;
                $draw->winners = DrawSelector::winners($seed, $tied, $spots);
                $draw->executed_by = $actor->id;
                $draw->executed_at = now();
                $draw->save();

                activity('competitions')->performedOn($draw)->causedBy($actor)
                    ->event(ActivityEvent::TieDrawExecuted->value)
                    ->withProperties(['round_id' => $round->id, 'category_id' => $categoryId, 'entrants_hash' => $draw->entrants_hash])
                    ->log(ActivityEvent::TieDrawExecuted->value);

                return [$draw, true];
            });
        } catch (UniqueConstraintViolationException) {
            // ضغطتان متزامنتان: الأولى سجّلت، والثانية تعيد سجلها
            return [$this->existing($round, $categoryId) ?? throw new AuthorizationException, false];
        }
    }

    public function existing(CompetitionRound $round, ?int $categoryId): ?CompetitionDraw
    {
        return CompetitionDraw::query()
            ->where('round_id', $round->id)
            ->when($categoryId === null, fn ($query) => $query->whereNull('category_id'), fn ($query) => $query->where('category_id', $categoryId))
            ->first();
    }

    /**
     * المسلِّمون بدرجتهم الكلية (الآلية والمقالي) تنازلياً، برقم تسجيلهم المرجعي.
     *
     * @return Collection<int, array{reference: string, total: float, max: float}>
     */
    public function ranked(CompetitionRound $round, ?int $categoryId): Collection
    {
        return CompetitionAttempt::query()
            ->where('round_id', $round->id)
            ->where('status', AttemptStatus::Submitted)
            ->whereHas('submission', fn ($query) => $query->whereNull('deleted_at')->whereNotNull('reference')
                ->when($categoryId !== null, fn ($query) => $query->where('category_id', $categoryId)))
            ->with('submission:id,reference')
            ->get(['id', 'submission_id', 'score', 'essay_score', 'max_score'])
            ->map(fn (CompetitionAttempt $attempt): array => [
                'reference' => (string) $attempt->submission->reference,
                'total' => round((float) $attempt->score + (float) $attempt->essay_score, 2),
                'max' => round((float) $attempt->max_score, 2),
            ])
            ->sortByDesc('total')
            ->values();
    }
}
