<?php

namespace Database\Factories;

use App\Enums\InquiryStatus;
use App\Enums\InquiryType;
use App\Models\Inquiry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Inquiry>
 */
class InquiryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => Inquiry::newReference(),
            'user_id' => null,
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => null,
            'type' => InquiryType::Inquiry,
            'subject' => fake()->sentence(4),
            'message' => fake()->paragraph(),
            'status' => InquiryStatus::Received,
            'assigned_to' => null,
            'internal_note' => null,
        ];
    }
}
