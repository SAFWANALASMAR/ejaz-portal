<?php

namespace Database\Factories;

use App\Enums\ServiceRequestStatus;
use App\Models\Opening;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceRequest>
 */
class ServiceRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'opening_id' => Opening::factory(),
            'user_id' => User::factory(),
            'status' => ServiceRequestStatus::Received,
            'answers' => ['about' => fake()->sentence()],
            'files' => null,
            'submitted_at' => now(),
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => ServiceRequestStatus::Draft, 'submitted_at' => null]);
    }

    public function status(ServiceRequestStatus $status): static
    {
        return $this->state(['status' => $status]);
    }
}
