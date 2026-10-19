<?php

namespace App\Services\Assistant;

use App\Contracts\AiAssistant;
use App\Enums\AssistantProvider;
use App\Models\AssistantSettings;
use RuntimeException;

/**
 * يبني تطبيق المزود من الإعدادات (المحفوظة، أو المعروضة في اللوحة عند «اختبار الاتصال»). والوهمي مرفوض في الإنتاج.
 * وطبقة المزود هذه مشتركة: المساعد وتحليل الإحصائيات يستعملانها بالاشتراك والسقف نفسيهما.
 */
final class AssistantProviders
{
    public static function make(AssistantSettings $settings): AiAssistant
    {
        if (! $settings->hasUsableProvider()) {
            throw new RuntimeException('The AI assistant provider is not configured.');
        }

        $baseUrl = (string) $settings->baseUrl();
        $key = (string) $settings->apiKey();
        $model = (string) $settings->model;

        return match ($settings->provider) {
            AssistantProvider::Fake => new FakeAiAssistant,
            AssistantProvider::Anthropic => new AnthropicAiAssistant($baseUrl, $key, $model),
            AssistantProvider::OpenAiCompatible => new OpenAiCompatibleAiAssistant($baseUrl, $key, $model),
            null => throw new RuntimeException('The AI assistant provider is not configured.'),
        };
    }
}
