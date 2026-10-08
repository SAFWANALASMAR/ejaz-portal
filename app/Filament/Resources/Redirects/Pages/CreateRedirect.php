<?php

namespace App\Filament\Resources\Redirects\Pages;

use App\Actions\Content\SaveRedirect;
use App\Filament\Concerns\SavesThroughAction;
use App\Filament\Resources\Redirects\RedirectResource;
use App\Models\Redirect;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateRedirect extends CreateRecord
{
    use SavesThroughAction;

    protected static string $resource = RedirectResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $actor */
        $actor = auth()->user();
        $redirect = new Redirect;

        return $this->saveThroughAction($redirect, [], fn (): Redirect => app(SaveRedirect::class)->handle($actor, $redirect, $data));
    }
}
