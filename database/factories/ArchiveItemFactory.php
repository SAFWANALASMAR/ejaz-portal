<?php

namespace Database\Factories;

use App\Enums\ArchiveItemType;
use App\Models\ArchiveCategory;
use App\Models\ArchiveItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ArchiveItem>
 */
class ArchiveItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $date = now()->subMonth();

        return [
            'type' => ArchiveItemType::Document,
            'archive_category_id' => ArchiveCategory::factory(),
            'title' => 'وثيقة تجريبية '.fake()->unique()->numberBetween(1, 9999),
            'entity' => 'جهة تجريبية',
            'dated_on' => $date->toDateString(),
            'year' => $date->year,
            'description' => null,
            'files' => null,
        ];
    }
}
