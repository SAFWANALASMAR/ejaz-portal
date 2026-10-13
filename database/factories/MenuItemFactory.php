<?php

namespace Database\Factories;

use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuItem>
 */
class MenuItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'parent_id' => null,
            'label' => fake()->words(2, true),
            'url' => '/'.fake()->slug(2),
            'opens_in_new_tab' => false,
            'is_visible' => true,
            'sort' => 0,
        ];
    }
}
