<?php

namespace Database\Factories;

use App\Enums\SaudiRegion;
use App\Enums\SubmissionStatus;
use App\Models\Competition;
use App\Models\CompetitionSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompetitionSubmission>
 */
class CompetitionSubmissionFactory extends Factory
{
    /**
     * مسودة لمتقدم جديد في مسابقة مفتوحة.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'competition_id' => Competition::factory(),
            'user_id' => User::factory(),
            'category' => null,
            'region' => SaudiRegion::Makkah,
            'answers' => [],
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn (): array => [
            'status' => SubmissionStatus::Submitted,
            'submitted_at' => now(),
            'reference' => strtoupper(fake()->unique()->bothify('??########')),
        ]);
    }
}
