<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * مزود الإجابات للمساعد الذكي (App\Contracts\AiAssistant). يُختار من اللوحة، والوهمي للتطوير والاختبارات وحدهما.
 */
enum AssistantProvider: string implements HasLabel
{
    case Fake = 'fake';
    case Anthropic = 'anthropic';
    case OpenAiCompatible = 'openai_compatible';

    public function getLabel(): string
    {
        return __('assistant.providers.'.$this->value);
    }

    /**
     * المزودون المتاحون في هذه البيئة: الوهمي لا يُعرض ولا يُقبل في الإنتاج.
     *
     * @return list<self>
     */
    public static function available(): array
    {
        return array_values(array_filter(self::cases(), fn (self $provider): bool => $provider !== self::Fake || ! app()->isProduction()));
    }

    /**
     * العنوان الأساسي لواجهة المزود متى تُرك فارغاً في الإعدادات. والمتوافق مع OpenAI يلزمه عنوان صريح.
     */
    public function defaultBaseUrl(): ?string
    {
        return match ($this) {
            self::Anthropic => 'https://api.anthropic.com',
            default => null,
        };
    }
}
