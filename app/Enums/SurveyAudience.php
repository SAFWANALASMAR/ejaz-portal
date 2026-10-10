<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * الفئة المستهدفة بالاستبيان (requirements/07).
 */
enum SurveyAudience: string implements HasLabel
{
    case Beneficiaries = 'beneficiaries';
    case Trainees = 'trainees';
    case Members = 'members';
    case Partners = 'partners';
    case General = 'general';

    public function getLabel(): string
    {
        return __('surveys.audiences.'.$this->value);
    }
}
