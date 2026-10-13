<?php

namespace App\Filament\Resources\Programs\Pages;

use App\Filament\Resources\Programs\ProgramResource;
use App\Models\Program;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateProgram extends CreateRecord
{
    use SavesProgramThroughAction;

    protected static string $resource = ProgramResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return $this->saveProgram(new Program, $data);
    }

    /**
     * بعد الإنشاء إلى التعديل كما كان، لا إلى صفحة الاطلاع (ViewProgram) التي أُضيفت للموظفين.
     */
    protected function getRedirectUrl(): string
    {
        return ProgramResource::canEdit($this->getRecord())
            ? ProgramResource::getUrl('edit', ['record' => $this->getRecord()])
            : parent::getRedirectUrl();
    }
}
