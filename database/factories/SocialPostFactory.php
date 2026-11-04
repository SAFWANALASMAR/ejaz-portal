<?php

namespace Database\Factories;

use App\Enums\SocialPlatform;
use App\Enums\SocialPostKind;
use App\Models\SocialPost;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * منشورات تجريبية للاختبارات وبيانات التطوير، لا منشورات الجمعية.
 *
 * @extends Factory<SocialPost>
 */
class SocialPostFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'platform' => SocialPlatform::X,
            'kind' => SocialPostKind::Post,
            'body' => 'منشور تجريبي '.fake()->unique()->numberBetween(1, 99999),
            'posted_on' => now()->subDays(fake()->numberBetween(0, 60))->toDateString(),
            'url' => 'https://x.com/ejaz/status/'.fake()->unique()->numerify('##########'),
            'image' => null,
            'is_published' => true,
        ];
    }

    public function unpublished(): static
    {
        return $this->state(['is_published' => false]);
    }
}
