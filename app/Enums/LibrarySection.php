<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * أقسام المكتبة (requirements/01 — قسم 5). القيمة جزء من الرابط، وتقابل روابط الموقع الحالي
 * (content-inventory.md). ولكل قسم مصدره: رابط مقطع، أو ملف صوتي، أو ملف PDF.
 * والترتيب ترتيب الواجهة (البند R9)، والقيم الأربع محصورة بقيد في القاعدة، فقسم جديد يلزمه ترحيل
 * (docs/decisions/2026-11-02-library-four-sections.md).
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
     * المرئية والصوتية برابط يوتيوب لا بملف مرفوع، فلا تأخذ من سعة التخزين (ملاحظات المدير التنفيذي، 25 أكتوبر 2026)،
     * والكتب والأبحاث بملف PDF.
     */
    public function takesVideoUrl(): bool
    {
        return $this === self::Videos || $this === self::Audio;
    }

    /**
     * الصوتية رُفعت ملفاتٍ قبل قرار الروابط، فيبقى ما رُفع منها يعمل، ولا يُرفع جديد.
     */
    public function keepsLegacyFile(): bool
    {
        return $this === self::Audio;
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
