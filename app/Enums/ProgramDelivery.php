<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * طريقة تنفيذ البرنامج (requirements/03).
 */
enum ProgramDelivery: string implements HasLabel
{
    case InPerson = 'in_person';
    case Remote = 'remote';
    case Hybrid = 'hybrid';

    public function getLabel(): string
    {
        return __('programs.delivery.'.$this->value);
    }

    /**
     * عن بعد أو مدمج: له رابط اجتماع يصل المسجلين المؤكدين.
     */
    public function isOnline(): bool
    {
        return $this !== self::InPerson;
    }
}
