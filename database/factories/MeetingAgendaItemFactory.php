<?php

namespace Database\Factories;

use App\Enums\AgendaItemStatus;
use App\Models\Meeting;
use App\Models\MeetingAgendaItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MeetingAgendaItem>
 */
class MeetingAgendaItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'meeting_id' => Meeting::factory(),
            'position' => 1,
            'title' => 'بند تجريبي',
            'status' => AgendaItemStatus::NotStarted,
        ];
    }
}
