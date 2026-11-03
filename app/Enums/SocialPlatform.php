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
     * المنصة من خلية الاستيراد: اسمها العربي أو قيمتها اللاتينية.
     */
    public static function fromCell(string $cell): ?self
    {
        $cell = trim($cell);

        foreach (self::cases() as $platform) {
            if ($cell === $platform->getLabel() || strtolower($cell) === $platform->value) {
                return $platform;
            }
        }

        return null;
    }
}
