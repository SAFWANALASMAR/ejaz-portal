<?php

namespace App\Filament\Resources\Programs\Pages;

use App\Filament\Resources\Programs\ProgramResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

/**
 * الاطلاع على البرنامج ومسجليه لمن يتابع البرامج ولا يعدّلها (فئة الموظفين — programs.view)،
 * وفيها زرّا الحضور لمن له programs.attend.
 */
class ViewProgram extends ViewRecord
{
    protected static string $resource = ProgramResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
