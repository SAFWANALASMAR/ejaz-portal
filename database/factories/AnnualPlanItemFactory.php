<?php

namespace Database\Factories;

use App\Models\AnnualPlanItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnnualPlanItem>
 */
class AnnualPlanItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'year' => 2026,
            'department' => 'إدارة تجريبية',
            'type' => 'نوع تجريبي',
            'title' => 'بند تجريبي '.fake()->unique()->numberBetween(1, 99999),
            'target_beneficiaries' => 10,
        ];
    }
}
