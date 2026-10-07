<?php

namespace Database\Factories;

use App\Enums\FormFieldType;
use App\Enums\OpeningKind;
use App\Models\Opening;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Opening>
 */
class OpeningFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kind' => OpeningKind::Employment,
            'title' => fake()->sentence(3),
            'slug' => fake()->unique()->slug(2),
            'summary' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'closes_at' => null,
            'is_published' => true,
            'form_fields' => [
                ['key' => 'about', 'label' => 'نبذة', 'type' => FormFieldType::Textarea->value, 'required' => true],
                ['key' => 'cv', 'label' => 'السيرة الذاتية', 'type' => FormFieldType::Pdf->value, 'required' => false],
            ],
        ];
    }

    public function kind(OpeningKind $kind): static
    {
        return $this->state(['kind' => $kind]);
    }

    public function unpublished(): static
    {
        return $this->state(['is_published' => false]);
    }

    public function closed(): static
    {
        return $this->state(['closes_at' => now()->subDay()]);
    }
}
