<?php

namespace Database\Factories;

use App\Enums\FormEntryStatus;
use App\Models\FormEntry;
use App\Models\FormTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormEntry>
 */
class FormEntryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'form_template_version_id' => fn (): int => (int) FormTemplate::factory()->create()->current_version_id,
            'answers' => ['name' => 'اسم تجريبي'],
            'status' => FormEntryStatus::Draft,
            'created_by' => User::factory(),
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => FormEntryStatus::Approved,
            'approved_by' => User::factory(),
            'approved_at' => now(),
        ]);
    }
}
