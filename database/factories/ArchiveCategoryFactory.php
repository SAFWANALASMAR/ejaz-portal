<?php

namespace Database\Factories;

use App\Models\ArchiveCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ArchiveCategory>
 */
class ArchiveCategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'تصنيف تجريبي '.fake()->unique()->numberBetween(1, 9999),
            'roles' => ['secretary', 'board_member'],
        ];
    }

    /**
     * @param  list<string>  $roles
     */
    public function roles(array $roles): static
    {
        return $this->state(['roles' => $roles]);
    }
}
