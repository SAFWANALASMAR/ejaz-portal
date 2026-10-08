<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * تصنيفات مكتبة ملفات الأعضاء كما في المتطلبات (requirements/04).
 */
enum AssemblyFileCategory: string implements HasLabel
{
    case FinancialStatements = 'financial_statements';
    case Reports = 'reports';
    case Decisions = 'decisions';

    public function getLabel(): string
    {
        return __('assembly.file_categories.'.$this->value);
    }

    /**
     * أيقونة Heroicons (outline) لبلاطة التصنيف.
     */
    public function icon(): string
    {
        return match ($this) {
            self::FinancialStatements => 'chart-bar',
            self::Reports => 'document-text',
            self::Decisions => 'scale',
        };
    }
}
