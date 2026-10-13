<?php

namespace Database\Factories;

use App\Enums\ProgramDelivery;
use App\Models\Program;
use App\Models\ProgramType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Program>
 */
class ProgramFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'program_type_id' => ProgramType::factory(),
            'slug' => fake()->unique()->slug(2),
            'title' => fake()->sentence(3),
            'summary' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'starts_at' => now()->addDays(10),
            'ends_at' => now()->addDays(10)->addHours(3),
            'delivery' => ProgramDelivery::InPerson,
            'location' => fake()->city(),
            'seats' => 20,
            'registration_opens_at' => now()->subDay(),
            'registration_closes_at' => null,
            'has_waitlist' => false,
            'form_fields' => null,
            'is_published' => true,
        ];
    }

    public function unpublished(): static
    {
        return $this->state(['is_published' => false]);
    }

    public function remote(string $url = 'https://teams.microsoft.com/l/meetup-join/demo'): static
    {
        return $this->state(['delivery' => ProgramDelivery::Remote, 'location' => null, 'meeting_url' => $url]);
    }
}
