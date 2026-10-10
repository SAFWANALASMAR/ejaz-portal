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

    /**
     * يرفع رقم إصدار المسابقة فيتغير المفتاح: طلب قرأ الأسئلة القديمة قبل اعتماد الحفظ ثم كتبها بعد
     * المسح يكتبها بالمفتاح القديم، فلا تُقرأ بعده.
     */
    public static function forget(int $competitionId): void
    {
        Cache::forever(self::versionKey($competitionId), self::version($competitionId) + 1);
    }

    public static function key(int $competitionId): string
    {
        return 'competition-exam-questions:'.$competitionId.':v'.self::version($competitionId);
    }

    private static function version(int $competitionId): int
    {
        return (int) Cache::get(self::versionKey($competitionId), 0);
    }

    private static function versionKey(int $competitionId): string
    {
        return 'competition-exam-questions-version:'.$competitionId;
    }
}
