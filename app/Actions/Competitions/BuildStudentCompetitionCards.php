<?php

namespace App\Actions\Competitions;

use App\Models\Competition;
use App\Models\CompetitionAttempt;
use App\Models\CompetitionRound;
use App\Models\CompetitionSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * بطاقة مسابقة الطلاب أعلى «حسابي» لكل مسابقة طلاب منشورة غير محذوفة سجّل فيها صاحب الحساب (ملاحظات المدير التنفيذي، ليلة 4 نوفمبر
 * 2026، المجموعة ج): المرحلة الحالية وحالة زرها الواحد. والحالة بقواعد صفحة المرحلة والبدء نفسيهما (StudentExamController
 * وStartAttempt)، فلا تعرض البطاقة رابطاً يرفضه المسار ولا تخفي ما يقبله (مراجعة المشرف على #182، I2):
 * - start: المرحلة جارية ولم تُنشر نتائجها ولم يبدأها، ومتأهل إن كانت للمتأهلين. الزر إلى صفحة البدء.
 * - resume: بدأها ولم يسلّم ووقته باقٍ. الزر إلى صفحة المرحلة، وهي تحوّله إلى أول وحدة لم تكتمل.
 * - expired: بدأها وانتهى وقته ولم يسلّم. الزر إلى حالة المحاولة، وهي تسلّمها آلياً (L5).
 * - submitted: سلّم إجاباته. الزر إلى حالة المحاولة.
 * - upcoming: المرحلة لم تبدأ، بموعد بدئها، بلا زر.
 * - not_qualified: مرحلة للمتأهلين ولم يتأهل من سابقتها (جارية، أو قادمة بعد نشر نتائج سابقتها)، بلا زر.
 * - ended: انتهت المرحلة أو نُشرت نتائجها ولم يبدأها، بلا زر.
 * - pending: «المواعيد تُعلن لاحقاً» مشغّل ولم يبدأها، بلا موعد ولا زر (StartAttempt يرفض البدء).
 * والمرحلة الحالية: الجارية، وإلا أقرب قادمة، وإلا آخر المراحل. والتسجيل الملغى (محذوف ناعماً) خارجها. والتأهل من محاولاته المحمّلة
 * في المرحلة السابقة (qualified_at)، كـ StartAttempt::qualified بلا استعلام لكل مرحلة.
 */
class BuildStudentCompetitionCards
{
    /**
     * @return list<array{competition: Competition, round: CompetitionRound, state: string, url: ?string, starts_at: Carbon}>
     */
    public function handle(User $user): array
    {
        $submissions = CompetitionSubmission::query()
            ->where('user_id', $user->id)
            ->whereNotNull('registered_at')
            // والمسابقة المحذوفة حذفاً ناعماً خارجها: علاقة competition() بـ withTrashed (مراجعة المشرف على #182، I1)
            ->whereHas('competition', fn ($competition) => $competition->withoutTrashed()->published()->where('student_mode', true))
            ->with(['competition.rounds', 'attempts' => fn ($attempts) => $attempts->where('user_id', $user->id)])
            ->latest('registered_at')
            ->get();

        $cards = [];

        foreach ($submissions as $submission) {
            $competition = $submission->competition;
            $rounds = $competition->rounds->values();
            $round = self::currentRound($rounds);

            if ($round === null) {
                continue;
            }

            $index = $rounds->search(fn (CompetitionRound $item): bool => $item->is($round));
            $previous = $index > 0 ? $rounds->get($index - 1) : null;
            /** @var CompetitionAttempt|null $attempt */
            $attempt = $submission->attempts->firstWhere('round_id', $round->id);
            $qualified = $previous !== null && $submission->attempts->contains(fn (CompetitionAttempt $item): bool => $item->round_id === $previous->id && $item->qualified_at !== null);
            // «المواعيد تُعلن لاحقاً»: لا موعد ولا زر بدء قبل اعتماد المواعيد، والمحاولة القائمة (إن وُجدت قبل الخيار) بحالتها
            $state = $competition->schedule_pending && $attempt === null ? 'pending' : self::state($round, $attempt, $qualified, $previous);

            $cards[] = [
                'competition' => $competition,
                'round' => $round,
                'state' => $state,
                'url' => match ($state) {
                    'start', 'resume' => route('competitions.student.round', [$competition->slug, $round->uuid]),
                    'submitted', 'expired' => route('competitions.student.status', [$competition->slug, $round->uuid]),
                    default => null,
                },
                'starts_at' => $round->starts_at,
            ];
        }

        return $cards;
    }

    /**
     * @param  Collection<int, CompetitionRound>  $rounds
     */
    private static function currentRound(Collection $rounds): ?CompetitionRound
    {
        return $rounds->first(fn (CompetitionRound $round): bool => $round->isRunning())
            ?? $rounds->filter(fn (CompetitionRound $round): bool => $round->starts_at->isFuture())->sortBy('starts_at')->first()
            ?? $rounds->last();
    }

    private static function state(CompetitionRound $round, ?CompetitionAttempt $attempt, bool $qualified, ?CompetitionRound $previous): string
    {
        if ($attempt !== null) {
            return match (true) {
                ! $attempt->isInProgress() => 'submitted',
                $attempt->expired() => 'expired',
                default => 'resume',
            };
        }

        // للمتأهلين: لا يُعرف عدم التأهل في القادمة قبل نشر نتائج سابقتها، فتبقى «تبدأ المرحلة في …» حتى ذلك
        $notQualified = $round->qualified_only && ! $qualified && ($round->isRunning() || ($previous?->resultsPublished() ?? true));

        return match (true) {
            $round->resultsPublished() => 'ended',
            $notQualified && ! $round->hasEnded() => 'not_qualified',
            $round->isRunning() => 'start',
            $round->starts_at->isFuture() => 'upcoming',
            default => 'ended',
        };
    }
}
