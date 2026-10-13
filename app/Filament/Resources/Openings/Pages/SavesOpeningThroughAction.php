<?php

namespace App\Filament\Resources\Openings\Pages;

use App\Actions\Services\SaveOpening;
use App\Filament\Concerns\SavesThroughAction;
use App\Models\Opening;
use App\Models\User;

/**
 * صفحتا إنشاء الفرصة وتعديلها تحفظان عبر SaveOpening (SavesThroughAction).
 */
trait SavesOpeningThroughAction
{
    use SavesThroughAction;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function saveOpening(Opening $opening, array $data): Opening
    {
        /** @var User $actor */
        $actor = auth()->user();

        return $this->saveThroughAction($opening, [], fn (): Opening => app(SaveOpening::class)->handle($actor, $opening, $data));
    }
}
