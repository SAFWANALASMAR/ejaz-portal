<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * جمهور النموذج الإلكتروني: يحدد من يُربط به المعبأ («حفظ النماذج تلقائيًا في ملفات الموظفين أو
 * المشاركين» — requirements/12 البند 10). والمشارك يرى المعتمد من نماذجه في لوحته.
 */
enum FormAudience: string implements HasLabel
{
    case Staff = 'staff';
    case Participants = 'participants';

    public function getLabel(): string
    {
        return __('documents.audiences.'.$this->value);
    }
}
