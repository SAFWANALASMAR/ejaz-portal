<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * غرض عملية الدفع (requirements/08-store-payments.md). التبرع مبني الآن، ورسوم العضوية والبرامج
 * تُربط بسجلها (payable) مع وحدتيهما.
 */
enum PaymentPurpose: string implements HasLabel
{
    case Donation = 'donation';
    case MembershipFee = 'membership_fee';
    case ProgramFee = 'program_fee';

    public function getLabel(): string
    {
        return __('payments.purposes.'.$this->value);
    }
}
