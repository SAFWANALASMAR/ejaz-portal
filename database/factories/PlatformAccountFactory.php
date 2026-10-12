<?php

namespace Database\Factories;

use App\Models\PlatformAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformAccount>
 */
class PlatformAccountFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'platform' => 'منصة تجريبية '.fake()->unique()->numberBetween(1, 99999),
            'username' => 'demo-user',
            'login_url' => 'https://example.test/login',
        ];
    }
}
