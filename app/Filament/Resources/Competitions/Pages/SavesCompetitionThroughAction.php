<?php

namespace App\Filament\Resources\Competitions\Pages;

use App\Actions\Competitions\SaveCompetition;
use App\Filament\Concerns\SavesThroughAction;
use App\Models\Competition;
use App\Models\User;

/**
 * صفحتا إنشاء المسابقة وتعديلها تحفظان عبر SaveCompetition (SavesThroughAction).
 */
trait SavesCompetitionThroughAction
{
    use SavesThroughAction;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function saveCompetition(Competition $competition, array $data): Competition
    {
        /** @var User $actor */
        $actor = auth()->user();

        return $this->saveThroughAction($competition, [], fn (): Competition => app(SaveCompetition::class)->handle($actor, $competition, $data));
    }
}
