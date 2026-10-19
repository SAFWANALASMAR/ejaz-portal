<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * مزودو الرسائل النصية السعوديون المدعومون، ويُختار أحدهم من «قنوات التواصل» في اللوحة (docs/guides/messaging.md).
 */
enum SmsProvider: string implements HasLabel
{
    case Taqnyat = 'taqnyat';
    case Unifonic = 'unifonic';
    case Msegat = 'msegat';

    public function getLabel(): string
    {
        return __('messaging.sms.providers.'.$this->value);
    }

    /**
     * Msegat وحده يطلب اسم المستخدم مع المفتاح.
     */
    public function needsUsername(): bool
    {
        return $this === self::Msegat;
    }
}
