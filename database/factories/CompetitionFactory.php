<?php

namespace Database\Factories;

use App\Enums\QuestionType;
use App\Models\Competition;
use App\Models\CompetitionCategory;
use App\Models\CompetitionRound;
use Database\Seeders\CompetitionListsSeeder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Competition>
 */
class CompetitionFactory extends Factory
{
    /**
     * مسابقة منشورة مفتوحة الآن. والحالات تغيّر موعديها أو تضيف أسئلتها ومعاييرها.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(2),
            'title' => fake()->sentence(3),
            'description' => null,
            'terms' => null,
            'prizes' => null,
            'categories' => ['الفئة الأولى', 'الفئة الثانية'],
            'opens_at' => now()->subDay(),
            'closes_at' => now()->addWeek(),
            'shuffle_questions' => false,
            'shuffle_options' => false,
            'is_published' => true,
        ];
    }

    public function upcoming(): static
    {
        return $this->state(['opens_at' => now()->addDay(), 'closes_at' => now()->addWeek()]);
    }

    public function closed(): static
    {
        return $this->state(['opens_at' => now()->subWeek(), 'closes_at' => now()->subDay()]);
    }

    public function unpublished(): static
    {
        return $this->state(['is_published' => false]);
    }

    /**
     * اختبار إلكتروني (docs/plans/competition-exam.md): بلا فئات، وفيديو تعريفي مدته 30 ثانية (ملفه يضعه
     * الاختبار على القرص)، وثلاثة أسئلة اختيار بأربعة خيارات، الصحيح فيها c. والأول معه نص علمي.
     */
    public function exam(): static
    {
        return $this->state([
            'exam_mode' => true,
            'categories' => [],
            'intro_video_path' => 'competition-media/intro.mp4',
            'intro_video_seconds' => 30,
        ])->afterCreating(function (Competition $competition): void {
            foreach (range(1, 3) as $number) {
                $competition->questions()->create([
                    'type' => QuestionType::MultipleChoice,
                    'prompt' => 'سؤال الاختبار التجريبي '.$number,
                    'context_text' => $number === 1 ? 'نص علمي تجريبي' : null,
                    'options' => [
                        ['key' => 'a', 'label' => 'خيار أ'.$number],
                        ['key' => 'b', 'label' => 'خيار ب'.$number],
                        ['key' => 'c', 'label' => 'خيار ج'.$number],
                        ['key' => 'd', 'label' => 'خيار د'.$number],
                    ],
                    'correct_option' => 'c',
                    'points' => 1,
                    'is_required' => true,
                    'sort_order' => $number,
                ]);
            }
        });
    }

    /**
     * مسابقة الطلاب (docs/plans/student-competition.md): قائمتا الفئات والمناطق من بذرتهما، والفئات كلها مفتوحة،
     * ومرحلة جارية الآن بعشرة أسئلة وثلاثين دقيقة، بلا أسئلة (يضيفها الاختبار بما يحتاج).
     */
    public function student(): static
    {
        return $this->state(['student_mode' => true, 'categories' => []])
            ->afterCreating(function (Competition $competition): void {
                (new CompetitionListsSeeder)->run();
                $competition->openCategories()->sync(CompetitionCategory::query()->pluck('id')->all());

                $round = new CompetitionRound([
                    'name' => 'المرحلة الأولى',
                    'sort_order' => 1,
                    'starts_at' => now()->subHour(),
                    'ends_at' => now()->addHours(3),
                    'question_count' => 10,
                    'duration_minutes' => 30,
                ]);
                $round->competition_id = $competition->id;
                $round->save();
            });
    }

    /**
     * سؤال نصي، وسؤال اختيار (الصحيح b بدرجتين)، وسؤال فيديو اختياري، ومعياران.
     */
    public function withQuestions(): static
    {
        return $this->afterCreating(function (Competition $competition): void {
            $competition->questions()->createMany([
                ['type' => QuestionType::Text, 'prompt' => 'سؤال نصي تجريبي', 'is_required' => true, 'sort_order' => 1],
                [
                    'type' => QuestionType::MultipleChoice,
                    'prompt' => 'سؤال اختيار تجريبي',
                    'options' => [['key' => 'a', 'label' => 'الخيار الأول'], ['key' => 'b', 'label' => 'الخيار الثاني']],
                    'correct_option' => 'b',
                    'points' => 2,
                    'is_required' => true,
                    'sort_order' => 2,
                ],
                ['type' => QuestionType::Video, 'prompt' => 'سؤال فيديو تجريبي', 'is_required' => false, 'max_video_seconds' => 120, 'max_video_mb' => 50, 'sort_order' => 3],
            ]);

            $competition->criteria()->createMany([
                ['name' => 'معيار أول', 'weight' => 60, 'max_score' => 10, 'sort_order' => 1],
                ['name' => 'معيار ثانٍ', 'weight' => 40, 'max_score' => 5, 'sort_order' => 2],
            ]);
        });
    }
}
