<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * نوع الإعلان للتصفية (ملاحظات المدير التنفيذي، 25 أكتوبر 2026): منتجات المتجر، والبرامج، والدورات، وعام. وإعلان المتجر
 * بلا رابط يفتح متجر الجمعية برابطه من الإعدادات (Announcement::href).
 */
enum AnnouncementKind: string implements HasLabel
{
    case Store = 'store';
    case Programs = 'programs';
    case Courses = 'courses';
    case General = 'general';

    public function getLabel(): string
    {
        return __('announcements.kinds.'.$this->value);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $kind): string => $kind->value, self::cases());
    }
}
