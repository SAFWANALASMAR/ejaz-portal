<?php

namespace Database\Factories;

use App\Enums\QuestionType;
use App\Models\Competition;
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
