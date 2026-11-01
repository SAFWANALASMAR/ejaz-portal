<?php

namespace Database\Factories;

use App\Enums\DocumentCategory;
use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(2),
            'title' => fake()->sentence(3),
            'category' => DocumentCategory::Policies,
            'issued_at' => fake()->date(),
            'version' => null,
            'file' => Document::DIRECTORY.'/'.fake()->uuid().'.pdf',
            'file_size' => 1024,
            'description' => null,
            'is_published' => true,
        ];
    }

    public function unpublished(): static
    {
        return $this->state(['is_published' => false]);
    }

    /**
     * وثيقة برابط مباشر بدل الملف.
     */
    public function link(string $url = 'https://example.org/report'): static
    {
        return $this->state(['file' => null, 'file_size' => null, 'url' => $url]);
    }
}
