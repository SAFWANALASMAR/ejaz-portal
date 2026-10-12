<?php

namespace Database\Factories;

use App\Enums\JobFileIcon;
use App\Enums\RoleName;
use App\Models\JobFile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobFile>
 */
class JobFileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => 'ملف تجريبي '.fake()->unique()->numberBetween(1, 9999),
            'description' => null,
            'responsible_role' => RoleName::FinancialManager,
            'participant_roles' => [],
            'participants_contribute' => false,
            'icon' => JobFileIcon::Folder,
            'sort' => 0,
        ];
    }

    /**
     * @param  list<RoleName>  $participants
     */
    public function responsible(RoleName $role, array $participants = [], bool $contribute = false): static
    {
        return $this->state([
            'responsible_role' => $role,
            'participant_roles' => RoleName::values($participants),
            'participants_contribute' => $contribute,
        ]);
    }
}
