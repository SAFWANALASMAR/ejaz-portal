<?php

namespace Database\Factories;

use App\Enums\RedirectType;
use App\Models\Redirect;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Redirect>
 */
class RedirectFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source' => 'old-'.fake()->unique()->slug(2),
            'target' => '/contact',
            'type' => RedirectType::Permanent,
            'is_active' => true,
        ];
    }

    public function temporary(): static
    {
        return $this->state(['type' => RedirectType::Temporary]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
