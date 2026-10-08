<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * حالة النموذج المعبأ: مسودة تُعدَّل، ثم معتمد يُقفل (requirements/05: التعديل قبل الاعتماد).
 */
enum FormEntryStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Approved = 'approved';

    public function getLabel(): string
    {
        return __('documents.entry_statuses.'.$this->value);
    }

    public function getColor(): string
    {
        return $this === self::Approved ? 'success' : 'gray';
    }
}
