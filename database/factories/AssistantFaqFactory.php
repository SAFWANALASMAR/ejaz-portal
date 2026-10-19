<?php

namespace Database\Factories;

use App\Enums\AssistantReviewStatus;
use App\Models\AssistantFaq;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssistantFaq>
 */
class AssistantFaqFactory extends Factory
{
    /**
     * نصوص تجريبية للاختبارات، لا أسئلة الجمعية.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'question' => 'سؤال تجريبي '.fake()->unique()->numberBetween(1, 99999),
            'answer' => 'جواب تجريبي للاختبار.',
            'keywords' => null,
            'source_url' => '/contact',
            'status' => AssistantReviewStatus::Approved,
            'sort' => 0,
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => AssistantReviewStatus::Draft]);
    }
}
