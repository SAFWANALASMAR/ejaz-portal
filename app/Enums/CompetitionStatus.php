<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * حالة المسابقة، تُحسب من النشر وموعدي الفتح والإغلاق (Competition::status)، فيُفتح التقديم
 * ويُغلق آلياً بلا مهمة مجدولة. وألوان الشارات من المرجع البصري (06): مفتوح أخضر، وقريباً ذهبي،
 * ومكتمل رمادي.
 */
enum CompetitionStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Upcoming = 'upcoming';
    case Open = 'open';
    case Closed = 'closed';

    public function getLabel(): string
    {
        return __('competitions.statuses.'.$this->value);
    }

    /**
     * الحالة بصياغة التسجيل للزائر في شريحة الرئيسية («التسجيل مفتوح» لا «مفتوحة» — ملاحظة صفوان ٣٠).
     */
    public function registrationLabel(): string
    {
        return __('ui.feed.status.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft, self::Closed => 'gray',
            self::Upcoming => 'warning',
            self::Open => 'success',
        };
    }
}
