<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * أنواع الفرص التي يُقدَّم عليها في الخدمات الإلكترونية (requirements/02).
 */
enum OpeningKind: string implements HasLabel
{
    case Membership = 'membership';
    case Employment = 'employment';
    case Volunteering = 'volunteering';

    public function getLabel(): string
    {
        return __('services.kinds.'.$this->value);
    }

    /**
     * صفحة قائمة النوع في الموقع.
     */
    public function route(): string
    {
        return match ($this) {
            self::Membership => 'services.assembly-membership',
            self::Employment => 'services.jobs',
            self::Volunteering => 'services.volunteering',
        };
    }

    /**
     * معرّف صفحة المقدمة التي تُعرض أعلى قائمة النوع (Page::INTROS).
     */
    public function introSlug(): string
    {
        return match ($this) {
            self::Membership => 'assembly-membership',
            self::Employment => 'jobs',
            self::Volunteering => 'volunteering',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Membership => 'user-group',
            self::Employment => 'briefcase',
            self::Volunteering => 'hand-raised',
        };
    }
}
