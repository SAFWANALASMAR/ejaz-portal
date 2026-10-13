<?php

namespace Database\Factories;

use App\Enums\MembershipStatus;
use App\Enums\RoleName;
use App\Models\AssemblyMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * العضوية الفعّالة تمنح الدور كما يفعل SaveAssemblyMembership، فتبقى البيانات التجريبية متسقة.
 *
 * @extends Factory<AssemblyMembership>
 */
class AssemblyMembershipFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => MembershipStatus::Active,
            'joined_on' => now()->subYear()->toDateString(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (AssemblyMembership $membership): void {
            if ($membership->status === MembershipStatus::Active) {
                $membership->user->assignRole(RoleName::AssemblyMember->value);
            }
        });
    }

    public function status(MembershipStatus $status): static
    {
        return $this->state(['status' => $status]);
    }
}
