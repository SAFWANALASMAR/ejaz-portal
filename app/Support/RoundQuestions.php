<?php

namespace App\Support;

use App\Models\CompetitionPassage;
use App\Models\CompetitionQuestion;
use App\Models\CompetitionRound;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * بنك أسئلة المرحلة ونصوصها من الذاكرة المؤقتة، فلا يُستعلم عنها في كل صفحة لمئات الطلاب معاً
 * (docs/performance.md). والمخزَّن بلا الخيار الصحيح، فلا يصل إلى الذاكرة ولا إلى الصفحة (كـ ExamQuestions).
 * ويُمسح عند كل تغيير في البنك، والبنك مقفل بعد أول محاولة أصلاً.
 *
 * المفتاح بإصدار يتغير مع كل تعديل، فالبنك مخزَّن بلا مدة، والمبني منه يُحفظ في ذاكرة العملية لمفتاحه، فلا يُقرأ
 * ويُبنى مرتين في الطلب الواحد (البدء: الترتيب ثم فحص العدد). ويبقى في كل نداء قراءة صغيرة لرقم الإصدار، فتعديل
 * من عملية أخرى يُرى فوراً (مراجعة 26 أكتوبر، م٣ ون١). والمعاد للقراءة وحدها: كائناته مشتركة بين نداءات العملية.
 */
final class RoundQuestions
{
    /** @var array<int, array{key: string, bank: array{questions: Collection<int, CompetitionQuestion>, passages: Collection<int, CompetitionPassage>}}> */
    private static array $built = [];

    /**
     * @return array{questions: Collection<int, CompetitionQuestion>, passages: Collection<int, CompetitionPassage>}
     */
    public static function load(CompetitionRound $round): array
    {
        $key = self::key($round->id);

        if (isset(self::$built[$round->id]) && self::$built[$round->id]['key'] === $key) {
            return self::$built[$round->id]['bank'];
        }

        /** @var array{questions: list<array<string, mixed>>, passages: list<array<string, mixed>>} $rows */
        $rows = Cache::rememberForever($key, fn (): array => [
            'questions' => $round->questions()->get()
                ->map(fn (CompetitionQuestion $question): array => Arr::except($question->getAttributes(), ['correct_option']))
                ->all(),
            'passages' => $round->passages()->get()->map(fn (CompetitionPassage $passage): array => $passage->getAttributes())->all(),
        ]);

        $bank = [
            'questions' => CompetitionQuestion::hydrate($rows['questions'])->keyBy('id'),
            'passages' => CompetitionPassage::hydrate($rows['passages'])->keyBy('id'),
        ];
        // مفتاح واحد لكل مرحلة، فلا تكبر الذاكرة في عملية طويلة (عامل الطابور) مع كل إصدار
        self::$built[$round->id] = ['key' => $key, 'bank' => $bank];

        return $bank;
    }

    /**
     * يغيّر رقم إصدار المرحلة فيتغير المفتاح، فلا يكتب طلب متأخر البنك القديم بعد المسح (كـ ExamQuestions).
     * والرقم عشوائي لا متسلسل: بعد تفريغ الذاكرة المؤقتة لا يعود إصدار قديم فيطابق بنكاً محفوظاً في عملية.
     * والبنك القديم يُحذف قبل تغيير الإصدار، لأنه بلا مدة وRedis في الإنتاج لا يطرد إلا ذوات المدة (volatile-lru)،
     * فيبقى لكل مرحلة بنك واحد مخزَّن.
     */
    public static function forget(int $roundId): void
    {
        Cache::forget(self::key($roundId));
        Cache::forever(self::versionKey($roundId), Str::random(16));
    }

    public static function key(int $roundId): string
    {
        return 'competition-round-questions:'.$roundId.':v'.self::version($roundId);
    }

    private static function version(int $roundId): string
    {
        return (string) Cache::rememberForever(self::versionKey($roundId), fn (): string => Str::random(16));
    }

    private static function versionKey(int $roundId): string
    {
        return 'competition-round-questions-version:'.$roundId;
    }
}
