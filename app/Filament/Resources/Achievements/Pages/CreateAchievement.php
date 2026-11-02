<?php

namespace App\Filament\Resources\Achievements\Pages;

use App\Actions\Achievements\SaveAchievement;
use App\Filament\Concerns\SavesThroughAction;
use App\Filament\Resources\Achievements\AchievementResource;
use App\Models\Achievement;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateAchievement extends CreateRecord
{
    use SavesThroughAction;

    protected static string $resource = AchievementResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $actor */
        $actor = auth()->user();
        $achievement = new Achievement;

        return $this->saveThroughAction($achievement, ['attachment'], fn (): Achievement => app(SaveAchievement::class)->handle($actor, $achievement, $data));
    }
}
