<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * حالة نسخة من صفحة في «محرر الصفحات» (docs/decisions/2026-11-02-page-editor.md): مسودة واحدة على الأكثر للصفحة، ونسخة منشورة
 * واحدة على الأكثر، وما سواهما سابقة تبقى للرجوع إليها.
 */
enum PageRevisionStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';

    public function getLabel(): string
    {
        return __('page_editor.statuses.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'warning',
            self::Published => 'success',
            self::Archived => 'gray',
        };
    }
}
