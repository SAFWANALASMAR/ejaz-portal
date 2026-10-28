<?php

namespace Database\Factories;

use App\Enums\AnnouncementKind;
use App\Models\Announcement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Announcement>
 */
class AnnouncementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'image' => 'announcements/'.fake()->uuid().'.png',
            'url' => 'https://'.fake()->domainName(),
            'kind' => AnnouncementKind::General,
            'starts_on' => null,
            'ends_on' => null,
            'sort' => 0,
            'is_published' => true,
        ];
    }

    public function unpublished(): static
    {
        return $this->state(['is_published' => false]);
    }
}
