<?php

namespace Database\Factories;

use App\Enums\AssemblyFileAudience;
use App\Enums\AssemblyFileCategory;
use App\Models\AssemblyFile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AssemblyFile>
 */
class AssemblyFileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => 'ملف تجريبي '.fake()->unique()->numberBetween(1, 9999),
            'category' => AssemblyFileCategory::Reports,
            'audience' => AssemblyFileAudience::Members,
            'file' => AssemblyFile::DIRECTORY.'/'.Str::random(40).'.pdf',
            'file_size' => 1024,
        ];
    }

    public function forBoard(): static
    {
        return $this->state(['audience' => AssemblyFileAudience::Board]);
    }
}
