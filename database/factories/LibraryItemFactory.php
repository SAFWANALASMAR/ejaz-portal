<?php

namespace Database\Factories;

use App\Enums\LibrarySection;
use App\Models\LibraryItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LibraryItem>
 */
class LibraryItemFactory extends Factory
{
    /**
     * كتاب منشور بملف PDF. والحالات تغيّر القسم ومصدره.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(2),
            'title' => fake()->sentence(3),
            'section' => LibrarySection::Books,
            'description' => null,
            'author' => null,
            'published_on' => fake()->date(),
            'tags' => [],
            'thumbnail' => null,
            'video_url' => null,
            'file' => LibraryItem::FILE_DIRECTORY.'/'.fake()->uuid().'.pdf',
            'file_size' => 1024,
            'is_published' => true,
        ];
    }

    public function video(string $url = 'https://www.youtube.com/watch?v=abcdefghijk'): static
    {
        return $this->state(['section' => LibrarySection::Videos, 'video_url' => $url, 'file' => null, 'file_size' => null]);
    }

    public function audio(): static
    {
        return $this->state(fn (): array => ['section' => LibrarySection::Audio, 'file' => LibraryItem::FILE_DIRECTORY.'/'.fake()->uuid().'.mp3']);
    }

    public function unpublished(): static
    {
        return $this->state(['is_published' => false]);
    }
}
