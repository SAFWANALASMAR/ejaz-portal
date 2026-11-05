<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Fortify\RecoveryCode;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // كل الأعمدة صريحة، لأن actingAs في الاختبارات يعامل النموذج كأنه مقروء من القاعدة
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'phone' => null,
            'phone_verified_at' => null,
            'self_registered_at' => null,
            'password' => static::$password ??= Hash::make('password'),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'privacy_accepted_at' => now(),
            'is_active' => true,
            'last_login_at' => null,
            'deletion_requested_at' => null,
            'anonymized_at' => null,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * تحقق ثنائي مفعّل ومؤكَّد.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'two_factor_recovery_codes' => encrypt(json_encode(
                Collection::times(8, fn () => RecoveryCode::generate())->all()
            )),
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
