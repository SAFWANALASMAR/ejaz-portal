<?php

namespace Database\Factories;

use App\Models\ProgramType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProgramType>
 */
class ProgramTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->word(),
            'slug' => fake()->unique()->slug(1),
            'sort' => 0,
        ];
    }
}
