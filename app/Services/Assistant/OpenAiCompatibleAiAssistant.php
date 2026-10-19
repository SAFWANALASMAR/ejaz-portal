<?php

namespace App\Services\Assistant;

use App\Contracts\AiAssistant;
use App\Support\Assistant\AnswerComposer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * أي واجهة متوافقة مع OpenAI Chat Completions بعنوانها الأساسي (مثل https://api.openai.com/v1، أو نموذج مستضاف
 * داخل المملكة يدعم الصيغة). عميل HTTP في لارافيل بلا مكتبة، وطلب واحد بلا إعادة محاولة بمهلة كلية.
 */
class OpenAiCompatibleAiAssistant implements AiAssistant
{
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
                // لا تحويل، فلا يصل المفتاح ولا الطلب إلى غير العنوان المسموح (المراجعة الأمنية، ن1)
                ->withoutRedirecting()
                ->withToken($this->apiKey)
                ->post('/chat/completions', [
                    'model' => $this->model,
                    'max_tokens' => (int) config('assistant.max_output_tokens'),
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $message],
                    ],
                ]);
        } catch (ConnectionException $exception) {
            throw new AssistantProviderException('Chat completions request failed or timed out.', 0, $exception);
        }

        if (! $response->successful()) {
            throw new AssistantProviderException('Chat completions responded with HTTP '.$response->status().'.');
        }

        $usage = (array) $response->json('usage', []);
        $tokens = [(int) ($usage['prompt_tokens'] ?? 0), (int) ($usage['completion_tokens'] ?? 0)];

        if ($response->json('choices.0.finish_reason') === 'content_filter') {
            return AssistantReply::measured(AnswerComposer::NO_ANSWER, $tokens[0], $tokens[1], $system.$message);
        }

        $text = $response->json('choices.0.message.content');

        if (! is_string($text) || trim($text) === '') {
            throw new AssistantProviderException('Chat completions returned no text.');
        }

        return AssistantReply::measured($text, $tokens[0], $tokens[1], $system.$message);
    }
}
