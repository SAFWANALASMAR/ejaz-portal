<?php

namespace Database\Factories;

use App\Models\JobFile;
use App\Models\JobFileDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobFileDocument>
 */
class JobFileDocumentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'job_file_id' => JobFile::factory(),
            'title' => 'مستند تجريبي',
            'description' => null,
            'path' => JobFileDocument::DIRECTORY.'/'.fake()->uuid().'.pdf',
            'original_name' => 'مستند.pdf',
            'size' => 100,
        ];
    }
}
