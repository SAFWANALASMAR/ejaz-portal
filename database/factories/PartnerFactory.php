<?php

namespace Database\Factories;

use App\Enums\PartnerKind;
use App\Models\Partner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Partner>
 */
class PartnerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'logo' => null,
            'url' => 'https://'.fake()->domainName(),
            'sort' => 0,
            'kind' => PartnerKind::Partner,
            'is_published' => true,
        ];
    }

    public function unpublished(): static
    {
        return $this->state(['is_published' => false]);
    }
}
