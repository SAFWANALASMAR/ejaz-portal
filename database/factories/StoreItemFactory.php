<?php

namespace Database\Factories;

use App\Enums\StoreItemCategory;
use App\Models\StoreItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * عناصر متجر تجريبية للاختبارات وبيانات التطوير، لا منتجات الجمعية.
 *
 * @extends Factory<StoreItem>
 */
class StoreItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => 'عنصر تجريبي '.fake()->unique()->numberBetween(1, 99999),
            'description' => 'وصف تجريبي',
            'image' => null,
            'category' => StoreItemCategory::Donation,
            'salla_url' => 'https://ejaz.org.sa/p'.fake()->unique()->numerify('#########'),
            'salla_product_id' => null,
            'sort' => 0,
            'is_published' => true,
        ];
    }

    public function publication(): static
    {
        return $this->state(['category' => StoreItemCategory::Publication]);
    }

    public function unpublished(): static
    {
        return $this->state(['is_published' => false]);
    }
}
