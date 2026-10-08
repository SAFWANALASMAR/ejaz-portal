<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * من يطّلع على ملف في مكتبة الأعضاء: كل من يصل إلى منطقة الجمعية العمومية، أو مجلس الإدارة وحده
 * (عضو المجلس والسكرتير والمشرف العام).
 */
enum AssemblyFileAudience: string implements HasLabel
{
    case Members = 'members';
    case Board = 'board';

    public function getLabel(): string
    {
        return __('assembly.file_audiences.'.$this->value);
    }
}
