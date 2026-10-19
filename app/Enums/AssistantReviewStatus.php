<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * حالة السؤال الشائع ومقالة الدليل: مسودة، ثم «مراجَعة» يفعّلها المسؤول للمساعد بعد مراجعتها، ثم «معتمدة» من
 * الجمعية. والسؤال الشائع لا يقدّمه المساعد إلا معتمداً، والمقالة مراجَعة أو معتمدة (قرار صفوان، 19 أكتوبر 2026).
 */
enum AssistantReviewStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Reviewed = 'reviewed';
    case Approved = 'approved';

    public function getLabel(): string
    {
        return __('assistant.statuses.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Reviewed => 'warning',
            self::Approved => 'success',
        };
    }

    /**
     * @return list<string>
     */
    public static function guideActive(): array
    {
        return [self::Reviewed->value, self::Approved->value];
    }
}
