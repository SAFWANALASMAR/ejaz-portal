<?php

namespace App\Services\Assistant;

use App\Contracts\AiAssistant;
use App\Support\Assistant\AnswerComposer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Claude من Anthropic عبر Messages API بعميل HTTP في لارافيل، بلا مكتبة (قرار صفوان، 19 أكتوبر 2026).
 * طلب واحد بلا إعادة محاولة، بمهلة كلية (config/assistant.php)، فلا ينتظر الزائر أكثر منها.
 */
class AnthropicAiAssistant implements AiAssistant
{
    public const VERSION = '2023-06-01';

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly string $model,
    ) {}

    public function answer(string $system, string $message): AssistantReply
    {
        try {
            $response = Http::baseUrl($this->baseUrl)
                ->acceptJson()
                ->asJson()
                ->timeout((int) config('assistant.timeout'))
                ->connectTimeout((int) config('assistant.connect_timeout'))
                // لا تحويل: يُبقي Guzzle رأس x-api-key مع التحويل إلى نطاق آخر (المراجعة الأمنية، ن1)
                ->withoutRedirecting()
                ->withHeaders(['x-api-key' => $this->apiKey, 'anthropic-version' => self::VERSION])
                ->post('/v1/messages', [
                    'model' => $this->model,
                    'max_tokens' => (int) config('assistant.max_output_tokens'),
                    'system' => $system,
                    'messages' => [['role' => 'user', 'content' => $message]],
                ]);
        } catch (ConnectionException $exception) {
            throw AssistantProviderException::connection('Anthropic', $exception);
        }

        if (! $response->successful()) {
            throw new AssistantProviderException('Anthropic responded with HTTP '.$response->status().'.');
        }

        $usage = (array) $response->json('usage', []);
        $tokens = [(int) ($usage['input_tokens'] ?? 0), (int) ($usage['output_tokens'] ?? 0)];

        // رفض المزود الإجابة: كأنه لم يجد جواباً، فيرى الزائر الرد الاحتياطي
        if ($response->json('stop_reason') === 'refusal') {
            return AssistantReply::measured(AnswerComposer::NO_ANSWER, $tokens[0], $tokens[1], $system.$message);
        }

        $text = collect((array) $response->json('content', []))
            ->filter(fn ($block): bool => is_array($block) && ($block['type'] ?? null) === 'text' && is_string($block['text'] ?? null))
            ->map(fn (array $block): string => $block['text'])
            ->implode('');

        if (trim($text) === '') {
            throw new AssistantProviderException('Anthropic returned no text.');
        }

        return AssistantReply::measured($text, $tokens[0], $tokens[1], $system.$message);
    }
}
