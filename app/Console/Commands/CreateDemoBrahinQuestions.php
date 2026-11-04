<?php

namespace App\Console\Commands;

use App\Enums\QuestionType;
use App\Models\Competition;
use App\Models\CompetitionQuestion;
use App\Models\CompetitionRound;
use App\Support\RoundQuestions;
use Database\Seeders\BrahinCompetitionSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * أربعون سؤالاً تجريبياً في بنك مسابقة البراهين لرابط العرض والبيئة المحلية وحدهما (قرار صفوان، 3 نوفمبر 2026 — البند ٩ من
 * إضافة توجيه المدير، docs/decisions/2026-11-03-director-home.md): ليرى المدير الاختبار كاملاً. نص كل سؤال «سؤال تجريبي رقم N —
 * 【ناقص: نص السؤال المعتمد من الجمعية】» وخياراته «خيار أ/ب/ج/د (تجريبي)»، بلا أي محتوى علمي أو شرعي، فالوسم «تجريبي» ظاهر
 * في اللوحة وفي الاختبار. تُضاف إلى المرحلة الجارية، وإلا التالية، وإلا الأولى، ولا تتكرر. و--remove يحذفها كلها من البنك بأمر
 * واحد قبل فتح المسابقة للطلاب (ومعها إجاباتها التجريبية بالحذف المتتالي). والأمر كله، إضافةً وحذفاً، لا يعمل في الإنتاج كأوامر
 * العرض الأخرى (مراجعة المشرف على #157): لا يمس بنك أسئلة حقيقياً.
 */
#[Signature('ejaz:demo-brahin-questions {--remove : حذف الأسئلة التجريبية من البنك}')]
#[Description('إضافة 40 سؤالاً تجريبياً إلى بنك مسابقة البراهين لرابط العرض، أو حذفها بـ --remove')]
class CreateDemoBrahinQuestions extends Command
{
    public const COUNT = 40;

    public function handle(): int
    {
        if (! $this->laravel->environment('local', 'testing')) {
            $this->error(__('competitions.demo_questions.refused'));

            return self::FAILURE;
        }

        $competition = $this->competition();

        if ($this->option('remove')) {
            return $this->remove($competition);
        }

        if ($competition === null) {
            $this->error(__('competitions.demo_questions.no_competition'));

            return self::FAILURE;
        }

        if (self::demoQuestions($competition)->exists()) {
            $this->info(__('competitions.demo_questions.exists'));

            return self::SUCCESS;
        }

        $round = $this->targetRound($competition);

        if ($round === null) {
            $this->error(__('competitions.demo_questions.no_competition'));

            return self::FAILURE;
        }

        /** @var array<string, string> $labels */
        $labels = __('competitions.demo_questions.options');
        $options = array_map(fn (string $key, string $label): array => ['key' => $key, 'label' => $label], array_keys($labels), $labels);
        $start = (int) $round->questions()->max('sort_order');

        DB::transaction(function () use ($competition, $round, $options, $start): void {
            for ($number = 1; $number <= self::COUNT; $number++) {
                $question = new CompetitionQuestion([
                    'type' => QuestionType::MultipleChoice,
                    'prompt' => __('competitions.demo_questions.prompt', ['number' => $number]),
                    'options' => $options,
                    'correct_option' => 'a',
                    'points' => 1,
                    'is_required' => false,
                    'sort_order' => $start + $number,
                    'round_id' => $round->id,
                ]);
                $question->competition_id = $competition->id;
                $question->save();
            }
        });

        RoundQuestions::forget($round->id);
        $this->info(__('competitions.demo_questions.created', ['count' => self::COUNT, 'round' => $round->name]));

        return self::SUCCESS;
    }

    private function remove(?Competition $competition): int
    {
        $count = 0;

        if ($competition !== null) {
            $rounds = self::demoQuestions($competition)->whereNotNull('round_id')->distinct()->pluck('round_id');
            $count = self::demoQuestions($competition)->delete();
            $rounds->each(fn (int $round) => RoundQuestions::forget($round));
        }

        $this->info(__('competitions.demo_questions.removed', ['count' => $count]));

        return self::SUCCESS;
    }

    /**
     * @return Builder<CompetitionQuestion>
     */
    public static function demoQuestions(Competition $competition): Builder
    {
        $prefix = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], (string) __('competitions.demo_questions.prefix'));

        return CompetitionQuestion::query()->where('competition_id', $competition->id)->where('prompt', 'like', $prefix.'%');
    }

    private function competition(): ?Competition
    {
        /** @var array{slug: string} $data */
        $data = require database_path(BrahinCompetitionSeeder::DATA);

        return Competition::query()->where('slug', $data['slug'])->first();
    }

    /**
     * المرحلة الجارية الآن، وإلا أقرب مرحلة قادمة، وإلا الأولى.
     */
    private function targetRound(Competition $competition): ?CompetitionRound
    {
        $rounds = CompetitionRound::query()->where('competition_id', $competition->id)->orderBy('sort_order')->get();

        return $rounds->first(fn (CompetitionRound $round): bool => now()->between($round->starts_at, $round->ends_at))
            ?? $rounds->filter(fn (CompetitionRound $round): bool => $round->starts_at->isFuture())->sortBy('starts_at')->first()
            ?? $rounds->first();
    }
}
