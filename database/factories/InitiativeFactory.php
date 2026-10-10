<?php

namespace Database\Factories;

use App\Models\Initiative;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Initiative>
 */
class InitiativeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(1),
            'name' => fake()->words(2, true),
            'summary' => fake()->sentence(),
            'image' => null,
            'blocks' => [],
            'goals' => [],
            'gallery' => [],
            'sort' => 0,
            'is_published' => true,
        ];
    }

    public function unpublished(): static
    {
        return $this->state(['is_published' => false]);
    }
}
