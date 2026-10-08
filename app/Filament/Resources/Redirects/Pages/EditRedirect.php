<?php

namespace App\Filament\Resources\Redirects\Pages;

use App\Actions\Content\SaveRedirect;
use App\Filament\Concerns\SavesThroughAction;
use App\Filament\Resources\Redirects\RedirectResource;
use App\Models\Redirect;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditRedirect extends EditRecord
{
    use SavesThroughAction;

    protected static string $resource = RedirectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Redirect $record */
        /** @var User $actor */
        $actor = auth()->user();

        return $this->saveThroughAction($record, [], fn (): Redirect => app(SaveRedirect::class)->handle($actor, $record, $data));
    }
}
