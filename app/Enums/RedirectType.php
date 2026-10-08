<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * نوع التحويل: الدائم لما انتقل نهائياً (تنقل محركات البحث ترتيبه)، والمؤقت لما سيعود.
 */
enum RedirectType: string implements HasLabel
{
    case Permanent = 'permanent';
    case Temporary = 'temporary';

    public function getLabel(): string
    {
        return __('redirects.types.'.$this->value);
    }

    public function statusCode(): int
    {
        return match ($this) {
            self::Permanent => 301,
            self::Temporary => 302,
        };
    }
}
