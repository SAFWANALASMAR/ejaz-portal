<?php

namespace App\Enums;

use App\Models\EvidenceGallery;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * حالة رابط معرض الشواهد في اللوحة: تُحسب من EvidenceGallery::linkIsActive() ولا تُخزَّن. والساري هو ما يفتحه الرابط فعلاً،
 * وما سواه سبب توقفه، ومنه «مُصدره معطّل» حين عُطّل حساب من أصدره (مراجعة الجودة، 3 نوفمبر 2026).
 */
enum EvidenceLinkStatus: string implements HasColor, HasLabel
{
    case None = 'none';
    case Active = 'active';
    case Expired = 'expired';
    case Revoked = 'revoked';
    case IssuerDisabled = 'issuer_disabled';

    public static function of(EvidenceGallery $gallery): self
    {
        return match (true) {
            $gallery->token_hash === null => self::None,
            $gallery->linkIsActive() => self::Active,
            // حذف المعرض يلغي رابطه (EvidenceGallery::booted)
            $gallery->revoked_at !== null || $gallery->trashed() => self::Revoked,
            $gallery->expires_at !== null && ! $gallery->expires_at->isFuture() => self::Expired,
            default => self::IssuerDisabled,
        };
    }

    public function getLabel(): string
    {
        return __('evidence.status.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::None => 'gray',
            self::IssuerDisabled => 'warning',
            self::Expired, self::Revoked => 'danger',
        };
    }
}
