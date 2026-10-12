<?php

namespace App\Enums;

use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * أيقونة بطاقة ملف الوظيفة، من قائمة ثابتة يختار منها المدير التنفيذي.
 */
enum JobFileIcon: string implements HasIcon, HasLabel
{
    case Folder = 'folder';
    case Document = 'document';
    case Archive = 'archive';
    case Envelope = 'envelope';
    case Users = 'users';
    case Briefcase = 'briefcase';
    case Chart = 'chart';
    case Banknotes = 'banknotes';
    case Calculator = 'calculator';
    case Scale = 'scale';
    case Shield = 'shield';
    case Megaphone = 'megaphone';
    case Photo = 'photo';
    case Calendar = 'calendar';
    case Clipboard = 'clipboard';
    case Warning = 'warning';
    case Idea = 'idea';

    public function getLabel(): string
    {
        return __('job_files.icons.'.$this->value);
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Folder => Heroicon::OutlinedFolder,
            self::Document => Heroicon::OutlinedDocumentText,
            self::Archive => Heroicon::OutlinedArchiveBox,
            self::Envelope => Heroicon::OutlinedEnvelope,
            self::Users => Heroicon::OutlinedUserGroup,
            self::Briefcase => Heroicon::OutlinedBriefcase,
            self::Chart => Heroicon::OutlinedChartBar,
            self::Banknotes => Heroicon::OutlinedBanknotes,
            self::Calculator => Heroicon::OutlinedCalculator,
            self::Scale => Heroicon::OutlinedScale,
            self::Shield => Heroicon::OutlinedShieldCheck,
            self::Megaphone => Heroicon::OutlinedMegaphone,
            self::Photo => Heroicon::OutlinedPhoto,
            self::Calendar => Heroicon::OutlinedCalendarDays,
            self::Clipboard => Heroicon::OutlinedClipboardDocumentList,
            self::Warning => Heroicon::OutlinedExclamationTriangle,
            self::Idea => Heroicon::OutlinedLightBulb,
        };
    }
}
