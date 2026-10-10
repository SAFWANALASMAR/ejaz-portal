<?php

namespace Database\Factories;

use App\Models\Committee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Committee>
 */
class CommitteeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'لجنة تجريبية '.fake()->unique()->numberBetween(1, 99999),
        ];
    }
}
