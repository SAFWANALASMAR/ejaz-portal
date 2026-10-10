<?php

namespace App\Support;

use App\Models\Competition;
use App\Models\CompetitionQuestion;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

/**
 * أسئلة الاختبار من الذاكرة المؤقتة لكل مسابقة، فلا يُستعلم عنها في كل صفحة سؤال لآلاف المتقدمين
 * (docs/performance.md). والمخزَّن بلا الخيار الصحيح، فلا يصل إلى الذاكرة ولا إلى الصفحة. وتُمسح عند
 * كل حفظ للمسابقة (SaveCompetition)، والبنك مقفل بعد أول إجابة أصلاً.
 */
final class ExamQuestions
{
    public static function load(Competition $competition): void
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = Cache::remember(
            self::key($competition->id),
            now()->addMinutes((int) config('competitions.exam.cache_minutes')),
            fn (): array => $competition->questions()->get()
                ->map(fn (CompetitionQuestion $question): array => Arr::except($question->getAttributes(), ['correct_option']))
                ->all(),
        );

        $competition->setRelation('questions', CompetitionQuestion::hydrate($rows));
    }

    public static function forget(int $competitionId): void
    {
        Cache::forget(self::key($competitionId));
    }

    private static function key(int $competitionId): string
    {
        return 'competition-exam-questions:'.$competitionId;
    }
}
