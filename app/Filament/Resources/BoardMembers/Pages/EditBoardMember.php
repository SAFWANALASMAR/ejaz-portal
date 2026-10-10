<?php

namespace App\Filament\Resources\BoardMembers\Pages;

use App\Filament\Actions\DeleteContentAction;
use App\Filament\Concerns\SavesContentThroughAction;
use App\Filament\Resources\BoardMembers\BoardMemberResource;
use App\Models\BoardMember;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditBoardMember extends EditRecord
{
    use SavesContentThroughAction;

    protected static string $resource = BoardMemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteContentAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var BoardMember $record */
        return $this->saveContent($record, $data);
    }
}
