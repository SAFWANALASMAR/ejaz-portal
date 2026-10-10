<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * أقسام المكتبة (requirements/01 — قسم 5). القيمة جزء من الرابط، وتقابل روابط الموقع الحالي
 * (content-inventory.md). ولكل قسم مصدره: رابط مقطع، أو ملف صوتي، أو ملف PDF.
 */
enum LibrarySection: string implements HasLabel
{
    case Videos = 'videos';
    case Audio = 'audio';
    case Books = 'books';
    case Research = 'research';

    public function getLabel(): string
    {
        return __('library.sections.'.$this->value);
    }

    /**
     * أيقونة البلاطة والبطاقة بلا صورة.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Videos => 'play-circle',
            self::Audio => 'musical-note',
            self::Books => 'book-open',
            self::Research => 'academic-cap',
        };
    }

    /**
     * المرئية برابط مقطع، وما سواها بملف.
     */
    public function takesVideoUrl(): bool
    {
        return $this === self::Videos;
    }

    public function takesPdf(): bool
    {
        return $this === self::Books || $this === self::Research;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
