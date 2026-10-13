<?php

namespace App\Filament\Resources\BoardMembers\Pages;

use App\Filament\Concerns\SavesContentThroughAction;
use App\Filament\Resources\BoardMembers\BoardMemberResource;
use App\Models\BoardMember;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateBoardMember extends CreateRecord
{
    use SavesContentThroughAction;

    protected static string $resource = BoardMemberResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return $this->saveContent(new BoardMember, $data);
    }
}
