<?php

namespace Database\Factories;

use App\Enums\MeetingStatus;
use App\Enums\MeetingType;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Meeting>
 */
class MeetingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $starts = now()->addWeek()->setTime(16, 0);

        return [
            'title' => 'اجتماع تجريبي '.fake()->unique()->numberBetween(1, 9999),
            'type' => MeetingType::Ordinary,
            'starts_at' => $starts,
            'ends_at' => $starts->copy()->addHours(2),
            'location' => 'قاعة تجريبية',
            'is_online' => false,
            'agenda' => "بند تجريبي أول\nبند تجريبي ثانٍ",
            'status' => MeetingStatus::Scheduled,
        ];
    }

    public function online(): static
    {
        return $this->state(['is_online' => true, 'location' => null]);
    }

    public function held(): static
    {
        return $this->state(['status' => MeetingStatus::Held, 'starts_at' => now()->subWeek(), 'ends_at' => now()->subWeek()->addHours(2)]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => MeetingStatus::Cancelled]);
    }

    /**
     * محضر مرفوع لم يُعتمد بعد.
     */
    public function withDraftMinutes(?User $uploader = null): static
    {
        return $this->held()->state(fn (): array => [
            'minutes_file' => Meeting::MINUTES_DIRECTORY.'/'.Str::random(40).'.pdf',
            'minutes_size' => 1024,
            'minutes_uploaded_by' => $uploader->id ?? User::factory(),
        ]);
    }

    public function withApprovedMinutes(): static
    {
        return $this->withDraftMinutes()->state(fn (): array => [
            'minutes_approved_by' => User::factory(),
            'minutes_approved_at' => now(),
        ]);
    }
}
