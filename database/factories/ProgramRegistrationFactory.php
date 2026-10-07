<?php

namespace Database\Factories;

use App\Enums\RegistrationStatus;
use App\Models\Program;
use App\Models\ProgramRegistration;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProgramRegistration>
 */
class ProgramRegistrationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'program_id' => Program::factory(),
            'user_id' => User::factory(),
            'status' => RegistrationStatus::Confirmed,
            'answers' => null,
        ];
    }

    public function waitlisted(): static
    {
        return $this->state(['status' => RegistrationStatus::Waitlisted]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => RegistrationStatus::Cancelled, 'cancelled_at' => now()]);
    }
}
