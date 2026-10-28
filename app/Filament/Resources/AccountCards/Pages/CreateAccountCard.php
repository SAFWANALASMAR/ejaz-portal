<?php

namespace App\Filament\Resources\AccountCards\Pages;

use App\Filament\Resources\AccountCards\AccountCardResource;
use App\Models\AccountCard;
use Filament\Resources\Pages\CreateRecord;

class CreateAccountCard extends CreateRecord
{
    protected static string $resource = AccountCardResource::class;

    /**
     * الجديدة في آخر الترتيب.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return [...$data, 'sort' => (int) AccountCard::query()->max('sort') + 1];
    }
}
