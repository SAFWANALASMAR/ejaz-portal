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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * السحب بين المتساوين في الدرجة عند إعلان الجوائز (قرار المدير التنفيذي): يُرتَّب المسلِّمون في المرحلة (والفئة
 * إن اختيرت) بدرجتهم، فإن تساوى طلاب على حد المراكز حُصروا وسُحب منهم بقدر المراكز الباقية بالمولّد الآمن
 * وبذرة وبصمة مسجلة في سجل السحب الثابت (RunDraw وDrawSelector). وسحب نافذ واحد لكل مرحلة وفئة بقفل صف المرحلة:
 * إعادة الضغط تعيد النتيجة المسجلة نفسها ولا تغيّرها، ويُتحقق منه بـ ejaz:verify-draw. ويُلغى مرة واحدة بسبب
 * مسجّل (VoidTieDraw) فيُتاح سحب جديد.
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
        if (! CompetitionAccess::managesResults($actor, $round->competition) || ! $round->competition->student_mode) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($actor, $round, $categoryId, $places): array {
            // قفل صف المرحلة يسلسل السحبين المتزامنين والإلغاء (VoidTieDraw)، بدل فهرس فريد كان يشمل الملغى
            $locked = CompetitionRound::query()->whereKey($round->id)->lockForUpdate()->firstOrFail();
            $existing = $this->existing($locked, $categoryId);

            // سحب نافذ واحد لكل مرحلة وفئة بمراكزه: إعادة الضغط بالعدد نفسه تعرضه، وبعدد آخر تُرفض ولا تُغيّره
            if ($existing !== null) {
                if ((int) $existing->places !== $places) {
                    throw ValidationException::withMessages(['places' => __('competitions.student.draw_other_places', ['places' => (int) $existing->places])]);
                }

                return [$existing, false];
            }

            // عند إعلان الجوائز: بعد نشر نتائج المرحلة (ونشرها بعد انتهاء نافذتها)
            if (! $locked->resultsPublished()) {
                throw ValidationException::withMessages(['places' => __('competitions.student.draw_before_publish')]);
            }

            $draw = self::record($this->ranked($locked, $categoryId), $places);
            $draw->competition_id = $locked->competition_id;
            $draw->round_id = $locked->id;
            $draw->category_id = $categoryId;
            $draw->executed_by = $actor->id;
            $draw->save();

            activity('competitions')->performedOn($draw)->causedBy($actor)
                ->event(ActivityEvent::TieDrawExecuted->value)
                ->withProperties(['round_id' => $locked->id, 'category_id' => $categoryId, 'entrants_hash' => $draw->entrants_hash])
                ->log(ActivityEvent::TieDrawExecuted->value);

            return [$draw, true];
        });
    }

    /**
     * سجل السحب بلا حفظ من ترتيب تنازلي بالدرجة: يُحصر المتساوون على حد المراكز ويُسحب منهم بقدر الباقي بالمولّد
     * الآمن وبذرة وبصمة. لسحب المرحلة هنا، وللترتيب التراكمي (RunCumulativeTieDraw).
     *
     * @param  Collection<int, array{reference: string, total: float, max: float}>  $ranked
     *
     * @throws ValidationException
     */
    public static function record(Collection $ranked, int $places): CompetitionDraw
    {
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

        $draw = new CompetitionDraw;
        $draw->places = $places;
        $draw->tie_score = $boundary;
        $draw->seed = $seed;
        $draw->entrants = $tied;
        $draw->entrants_hash = DrawSelector::fingerprint($tied);
        $draw->entrants_count = count($tied);
        $draw->winners_count = $spots;
        $draw->winners = DrawSelector::winners($seed, $tied, $spots);
        $draw->executed_at = now();

        return $draw;
    }

    /**
     * السحب النافذ (غير الملغى) للمرحلة والفئة.
     */
    public function existing(CompetitionRound $round, ?int $categoryId): ?CompetitionDraw
    {
        return CompetitionDraw::query()
            ->where('round_id', $round->id)
            ->when($categoryId === null, fn ($query) => $query->whereNull('category_id'), fn ($query) => $query->where('category_id', $categoryId))
            ->whereDoesntHave('void')
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
