<?php

namespace App\Actions\Competitions;

use App\Models\Competition;
use App\Models\CompetitionAttempt;
use App\Models\CompetitionRound;
use App\Models\CompetitionSubmission;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * بطاقة مسابقة الطلاب أعلى «حسابي» لكل مسابقة طلاب منشورة سجّل فيها صاحب الحساب (ملاحظات المدير التنفيذي، ليلة 4 نوفمبر 2026،
 * المجموعة ج): المرحلة الحالية وحالة زرها الواحد. والحالة بقواعد صفحة المرحلة نفسها (StudentExamController):
 * - start: المرحلة جارية ولم يبدأها، فالزر إلى صفحة البدء.
 * - resume: بدأها ولم يسلّم ووقته باقٍ، فالزر إلى أول وحدة لم تكتمل (صفحة المرحلة تحوّله).
 * - submitted: سلّم إجاباته، والزر إلى حالة المحاولة.
 * - upcoming: المرحلة لم تبدأ، بموعد بدئها، بلا زر.
 * - ended: انتهى وقتها أو وقت محاولته ولم يسلّم، بلا زر. ومحاولته المنتهية تسلّمها صفحة المرحلة آلياً حين يفتحها.
 * والمرحلة الحالية: الجارية، وإلا أقرب قادمة، وإلا آخر المراحل. والتسجيل الملغى (محذوف ناعماً) خارجها.
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
            ->whereHas('competition', fn ($competition) => $competition->published()->where('student_mode', true))
            ->with(['competition.rounds', 'attempts' => fn ($attempts) => $attempts->where('user_id', $user->id)])
            ->latest('registered_at')
            ->get();

        $cards = [];

        foreach ($submissions as $submission) {
            $competition = $submission->competition;
            $round = self::currentRound($competition);

            if ($round === null) {
                continue;
            }

            /** @var CompetitionAttempt|null $attempt */
            $attempt = $submission->attempts->firstWhere('round_id', $round->id);
            $state = self::state($round, $attempt);

            $cards[] = [
                'competition' => $competition,
                'round' => $round,
                'state' => $state,
                'url' => match ($state) {
                    'start', 'resume' => route('competitions.student.round', [$competition->slug, $round->uuid]),
                    'submitted' => route('competitions.student.status', [$competition->slug, $round->uuid]),
                    default => null,
                },
                'starts_at' => $round->starts_at,
            ];
        }

        return $cards;
    }

    private static function currentRound(Competition $competition): ?CompetitionRound
    {
        $rounds = $competition->rounds;

        return $rounds->first(fn (CompetitionRound $round): bool => $round->isRunning())
            ?? $rounds->filter(fn (CompetitionRound $round): bool => $round->starts_at->isFuture())->sortBy('starts_at')->first()
            ?? $rounds->last();
    }

    private static function state(CompetitionRound $round, ?CompetitionAttempt $attempt): string
    {
        if ($attempt !== null && ! $attempt->isInProgress()) {
            return 'submitted';
        }

        if ($attempt !== null) {
            return $attempt->expired() ? 'ended' : 'resume';
        }

        return match (true) {
            $round->isRunning() => 'start',
            $round->starts_at->isFuture() => 'upcoming',
            default => 'ended',
        };
    }
}
