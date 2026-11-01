<?php

namespace Database\Factories;

use App\Models\EvidenceGallery;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EvidenceGallery>
 */
class EvidenceGalleryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => 'معرض شواهد تجريبي '.fake()->unique()->numberBetween(1, 9999),
            'description' => null,
        ];
    }
}
