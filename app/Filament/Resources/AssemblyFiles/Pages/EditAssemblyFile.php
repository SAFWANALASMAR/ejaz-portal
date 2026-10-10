<?php

namespace App\Filament\Resources\AssemblyFiles\Pages;

use App\Actions\Assembly\SaveAssemblyFile;
use App\Filament\Concerns\SavesThroughAction;
use App\Filament\Resources\AssemblyFiles\AssemblyFileResource;
use App\Models\AssemblyFile;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditAssemblyFile extends EditRecord
{
    use SavesThroughAction;

    protected static string $resource = AssemblyFileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AssemblyFileResource::downloadAction(),
            DeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $actor */
        $actor = auth()->user();
        /** @var AssemblyFile $record */

        return $this->saveThroughAction($record, ['file'], fn (): AssemblyFile => app(SaveAssemblyFile::class)->handle($actor, $record, $data));
    }
}
