<?php

namespace Database\Factories;

use App\Enums\RegisterEntryType;
use App\Models\RegisterEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RegisterEntry>
 */
class RegisterEntryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => RegisterEntryType::License,
            'title' => 'مستند تجريبي '.fake()->unique()->numberBetween(1, 99999),
            'issuer' => 'جهة تجريبية',
            'document_number' => null,
            'issued_on' => now()->subYear()->toDateString(),
            'expires_on' => now()->addYear()->toDateString(),
            'alert_days' => 30,
        ];
    }
}
