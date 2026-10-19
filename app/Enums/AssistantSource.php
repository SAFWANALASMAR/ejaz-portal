<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * مصادر فهرس المساعد: المحتوى المنشور وحده (requirements/10)، والأسئلة الشائعة المعتمدة، ودليل استخدام البوابة.
 */
enum AssistantSource: string implements HasLabel
{
    case Faq = 'faq';
    case Guide = 'guide';
    case Page = 'page';
    case Initiative = 'initiative';
    case Document = 'document';
    case Library = 'library';
    case News = 'news';
    case Program = 'program';
    case Competition = 'competition';
    case Opening = 'opening';

    public function getLabel(): string
    {
        return __('assistant.sources.'.$this->value);
    }

    /**
     * الأسئلة المعتمدة والدليل تُقدَّم على غيرها في الترتيب.
     */
    public function isCurated(): bool
    {
        return in_array($this, [self::Faq, self::Guide], true);
    }
}
