<?php

namespace App\Support;

use App\Models\CompetitionPassage;
use App\Models\CompetitionQuestion;
use App\Models\CompetitionRound;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

/**
 * بنك أسئلة المرحلة ونصوصها من الذاكرة المؤقتة، فلا يُستعلم عنها في كل صفحة لمئات الطلاب معاً
 * (docs/performance.md). والمخزَّن بلا الخيار الصحيح، فلا يصل إلى الذاكرة ولا إلى الصفحة (كـ ExamQuestions).
 * ويُمسح عند كل تغيير في البنك، والبنك مقفل بعد أول محاولة أصلاً.
 */
final class RoundQuestions
{
    /**
     * @return array{questions: Collection<int, CompetitionQuestion>, passages: Collection<int, CompetitionPassage>}
     */
    public static function load(CompetitionRound $round): array
    {
        /** @var array{questions: list<array<string, mixed>>, passages: list<array<string, mixed>>} $rows */
        $rows = Cache::remember(
            self::key($round->id),
            now()->addMinutes((int) config('competitions.exam.cache_minutes')),
            fn (): array => [
                'questions' => $round->questions()->get()
                    ->map(fn (CompetitionQuestion $question): array => Arr::except($question->getAttributes(), ['correct_option']))
                    ->all(),
                'passages' => $round->passages()->get()->map(fn (CompetitionPassage $passage): array => $passage->getAttributes())->all(),
            ],
        );

        return [
            'questions' => CompetitionQuestion::hydrate($rows['questions'])->keyBy('id'),
            'passages' => CompetitionPassage::hydrate($rows['passages'])->keyBy('id'),
        ];
    }

    /**
     * يرفع رقم إصدار المرحلة فيتغير المفتاح، فلا يكتب طلب متأخر البنك القديم بعد المسح (كـ ExamQuestions).
     */
    public static function forget(int $roundId): void
    {
        Cache::forever(self::versionKey($roundId), self::version($roundId) + 1);
    }

    public static function key(int $roundId): string
    {
        return 'competition-round-questions:'.$roundId.':v'.self::version($roundId);
    }

    private static function version(int $roundId): int
    {
        return (int) Cache::get(self::versionKey($roundId), 0);
    }

    private static function versionKey(int $roundId): string
    {
        return 'competition-round-questions-version:'.$roundId;
    }
}
