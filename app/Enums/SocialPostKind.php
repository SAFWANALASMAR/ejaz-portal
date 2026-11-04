<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * نوع منشور التواصل: منشور، أو حدث (فعالية أو لقاء نُشر على المنصة).
 */
enum SocialPostKind: string implements HasLabel
{
    case Post = 'post';
    case Event = 'event';

    public function getLabel(): string
    {
        return __('social_posts.kinds.'.$this->value);
    }

    /**
     * النوع من خلية الاستيراد: اسمه العربي أو قيمته اللاتينية.
     */
    public static function fromCell(string $cell): ?self
    {
        $cell = trim($cell);

        foreach (self::cases() as $kind) {
            if ($cell === $kind->getLabel() || strtolower($cell) === $kind->value) {
                return $kind;
            }
        }

        return null;
    }
}
