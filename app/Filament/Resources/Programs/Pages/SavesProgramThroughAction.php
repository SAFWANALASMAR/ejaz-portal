<?php

namespace App\Filament\Resources\Programs\Pages;

use App\Actions\Programs\SaveProgram;
use App\Filament\Concerns\SavesThroughAction;
use App\Models\Program;
use App\Models\User;

/**
 * صفحتا إنشاء البرنامج وتعديله تحفظان عبر SaveProgram (SavesThroughAction).
 */
trait SavesProgramThroughAction
{
    use SavesThroughAction;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function saveProgram(Program $program, array $data): Program
    {
        /** @var User $actor */
        $actor = auth()->user();

        return $this->saveThroughAction($program, ['image'], fn (): Program => app(SaveProgram::class)->handle($actor, $program, $data));
    }
}
