<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * منصة منشور التواصل في «منشوراتنا» (طلب صفوان، 3 نوفمبر 2026 — docs/decisions/2026-11-04-social-posts.md). القيم أسماء
 * شعاراتها في resources/svg/social وتسمياتها في settings.social، والنطاقات المسموحة لرابط المنشور الأصلي من كل منصة.
 */
enum SocialPlatform: string implements HasLabel
{
    case X = 'x';
    case Instagram = 'instagram';
    case Snapchat = 'snapchat';
    case TikTok = 'tiktok';
    case YouTube = 'youtube';
    case LinkedIn = 'linkedin';
    case Facebook = 'facebook';
    case WhatsApp = 'whatsapp';

    public function getLabel(): string
    {
        return __('settings.social.'.$this->value);
    }

    /**
     * نطاقات المنصة: المضيف أحدها أو نطاق فرعي منه (www.instagram.com، m.facebook.com).
     *
     * @return list<string>
     */
    public function hosts(): array
    {
        return match ($this) {
            self::X => ['x.com', 'twitter.com'],
            self::Instagram => ['instagram.com'],
            self::Snapchat => ['snapchat.com'],
            self::TikTok => ['tiktok.com'],
            self::YouTube => ['youtube.com', 'youtu.be'],
            self::LinkedIn => ['linkedin.com'],
            self::Facebook => ['facebook.com', 'fb.watch'],
            self::WhatsApp => ['whatsapp.com', 'wa.me'],
        };
    }

    /**
     * كتابات شائعة لأسماء المنصات في ملفات الاستيراد، بلا مسافات، غير التسمية المعتمدة.
     */
    private const ALIASES = [
        'تويتر' => self::X, 'twitter' => self::X,
        'انستقرام' => self::Instagram, 'إنستقرام' => self::Instagram, 'انستغرام' => self::Instagram, 'انستجرام' => self::Instagram,
        'سناب' => self::Snapchat,
        'تيكتوك' => self::TikTok,
        'لينكدان' => self::LinkedIn, 'لينكدإن' => self::LinkedIn,
        'فيسبوك' => self::Facebook, 'فيس' => self::Facebook,
        'وتساب' => self::WhatsApp,
    ];

    /**
     * المنصة من خلية الاستيراد: اسمها العربي، أو قيمتها اللاتينية، أو كتابة شائعة لها، بلا اعتبار للمسافات وحالة الأحرف.
     */
    public static function fromCell(string $cell): ?self
    {
        $key = mb_strtolower((string) preg_replace('/\s+/u', '', $cell));

        foreach (self::cases() as $platform) {
            if ($key === (string) preg_replace('/\s+/u', '', $platform->getLabel()) || $key === $platform->value) {
                return $platform;
            }
        }

        return self::ALIASES[$key] ?? null;
    }
}
