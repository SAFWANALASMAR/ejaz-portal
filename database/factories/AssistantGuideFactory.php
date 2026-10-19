<?php

namespace Database\Factories;

use App\Enums\AssistantReviewStatus;
use App\Models\AssistantGuide;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssistantGuide>
 */
class AssistantGuideFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => 'guide-'.fake()->unique()->numberBetween(1, 99999),
            'title' => 'مقالة تجريبية',
            'keywords' => null,
            'steps' => ['الخطوة الأولى التجريبية.', 'الخطوة الثانية التجريبية.'],
            'route_name' => 'contact',
            'status' => AssistantReviewStatus::Reviewed,
            'sort' => 0,
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => AssistantReviewStatus::Draft]);
    }
}
