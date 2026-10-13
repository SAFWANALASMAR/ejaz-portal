<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * تصنيفات وثائق الحوكمة (requirements/01 — قسم 4). القيمة تظهر في رابط التصفية.
 */
enum DocumentCategory: string implements HasLabel
{
    case Policies = 'policies';
    case AnnualReports = 'annual-reports';
    case FinancialReports = 'financial-reports';
    case Official = 'official';
    case Procedures = 'procedures';

    public function getLabel(): string
    {
        return __('governance.categories.'.$this->value);
    }

    /**
     * أيقونة البلاطة في صفحة الحوكمة (الصورة 05).
     */
    public function icon(): string
    {
        return match ($this) {
            self::Policies => 'shield-check',
            self::AnnualReports => 'document-text',
            self::FinancialReports => 'chart-bar',
            self::Official => 'folder',
            self::Procedures => 'clipboard-document-list',
        };
    }
}
