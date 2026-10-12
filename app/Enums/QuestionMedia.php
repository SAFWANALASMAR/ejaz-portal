<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * وسيط يصاحب سؤال الاختبار: صورة، أو مقطع مرئي، أو صوتي. والأنواع الحقيقية المقبولة لكل منها هنا.
 */
enum QuestionMedia: string implements HasLabel
{
    case Image = 'image';
    case Video = 'video';
    case Audio = 'audio';

    public function getLabel(): string
    {
        return __('competitions.media_types.'.$this->value);
    }

    /**
     * @return list<string>
     */
    public function mimeTypes(): array
    {
        return match ($this) {
            self::Image => ['image/jpeg', 'image/png', 'image/webp'],
            self::Video => ['video/mp4'],
            self::Audio => ['audio/mpeg', 'audio/mp4', 'audio/x-m4a'],
        };
    }
}
