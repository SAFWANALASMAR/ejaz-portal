<?php

namespace App\Filament\Resources\AccountCards\Pages;

use App\Filament\Resources\AccountCards\AccountCardResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAccountCard extends EditRecord
{
    protected static string $resource = AccountCardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
