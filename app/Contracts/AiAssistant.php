<?php

namespace App\Contracts;

use App\Services\Assistant\AssistantProviderException;
use App\Services\Assistant\AssistantReply;

/**
 * مزود الإجابات للمساعد الذكي (requirements/10). يتسلّم تعليمات النظام ورسالة واحدة فيها المقاطع المسترجعة والسؤال،
 * ولا شيء غيرها. والمزود يُختار من اللوحة (AssistantProvider): الوهمي للتطوير والاختبارات، وClaude من Anthropic،
 * وأي واجهة متوافقة مع OpenAI Chat Completions بعنوانها الأساسي.
 */
interface AiAssistant
{
    /**
     * @throws AssistantProviderException تعذّر المزود (مهلة، أو خطأ، أو رد بلا نص)
     */
    public function answer(string $system, string $message): AssistantReply;
}
