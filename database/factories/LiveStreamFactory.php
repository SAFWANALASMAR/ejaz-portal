<?php

namespace Database\Factories;

use App\Enums\LiveStreamProvider;
use App\Models\LiveStream;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LiveStream>
 */
class LiveStreamFactory extends Factory
{
    /**
     * بث يوتيوب منشور جارٍ الآن. والحالات تغيّر موعديه أو مزوده أو نشره.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'description' => null,
            'provider' => LiveStreamProvider::Youtube,
            'url' => 'https://www.youtube.com/watch?v=abcdefghijk',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'is_published' => true,
        ];
    }

    public function upcoming(): static
    {
        return $this->state(['starts_at' => now()->addHours(2), 'ends_at' => now()->addHours(4)]);
    }

    public function ended(): static
    {
        return $this->state(['starts_at' => now()->subHours(4), 'ends_at' => now()->subHours(2)]);
    }

    public function draft(): static
    {
        return $this->state(['is_published' => false]);
    }

    public function teams(): static
    {
        return $this->state(['provider' => LiveStreamProvider::Teams, 'url' => 'https://teams.microsoft.com/l/meetup-join/19%3ameeting_demo']);
    }
}
