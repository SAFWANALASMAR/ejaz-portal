<?php

namespace Database\Factories;

use App\Enums\BodyRole;
use App\Enums\MeetingBody;
use App\Models\BodyMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * عضوية فعّالة في مجلس الإدارة افتراضياً.
 *
 * @extends Factory<BodyMembership>
 */
class BodyMembershipFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'body' => MeetingBody::Board,
            'committee_id' => null,
            'user_id' => User::factory(),
            'role' => BodyRole::Member,
            'starts_on' => now()->subYear()->toDateString(),
            'ends_on' => null,
        ];
    }
}
