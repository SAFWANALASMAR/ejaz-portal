<?php

namespace App\Filament\Resources\Achievements\Pages;

use App\Actions\Achievements\SaveAchievement;
use App\Filament\Concerns\SavesThroughAction;
use App\Filament\Resources\Achievements\AchievementResource;
use App\Models\Achievement;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditAchievement extends EditRecord
{
    use SavesThroughAction;

    protected static string $resource = AchievementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AchievementResource::attachmentAction(),
            AchievementResource::deleteAction(),
            AchievementResource::restoreAction(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $actor */
        $actor = auth()->user();
        /** @var Achievement $record */

        return $this->saveThroughAction($record, ['attachment'], fn (): Achievement => app(SaveAchievement::class)->handle($actor, $record, $data));
    }
}
