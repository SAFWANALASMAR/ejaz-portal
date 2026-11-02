<?php

namespace Database\Factories;

use App\Enums\AchievementStatus;
use App\Models\Achievement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Achievement>
 */
class AchievementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => 'إنجاز تجريبي '.fake()->unique()->numberBetween(1, 99999),
            'achieved_on' => now(config('app.display_timezone'))->toDateString(),
            'details' => 'تفاصيل إنجاز تجريبي للاختبار.',
            'status' => AchievementStatus::Draft,
        ];
    }

    public function published(): static
    {
        return $this->state(['status' => AchievementStatus::Published]);
    }

    /**
     * بمرفق PDF في مجلده (المحتوى يضعه الاختبار في القرص).
     */
    public function withAttachment(string $extension = 'pdf'): static
    {
        return $this->state(fn (): array => [
            'attachment' => Achievement::DIRECTORY.'/'.fake()->uuid().'.'.$extension,
            'attachment_name' => 'مرفق.'.$extension,
            'attachment_size' => 100,
        ]);
    }
}
