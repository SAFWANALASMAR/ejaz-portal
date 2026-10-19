<?php

namespace App\Services\Assistant;

use App\Contracts\AiAssistant;
use App\Support\Assistant\AnswerComposer;
use Illuminate\Http\Client\ConnectionException;
use RuntimeException;

/**
 * للتطوير والاختبارات: يجيب من أول مقطع في الرسالة بلا اتصال، ويحفظ ما أُرسل إليه لتتحقق منه الاختبارات.
 * ولا يعمل في الإنتاج.
 */
class FakeAiAssistant implements AiAssistant
{
    /** @var list<array{system: string, message: string}> */
    public array $requests = [];

    public ?string $reply = null;

    public bool $fail = false;

    /**
     * تعذّر بانتهاء المهلة، فيُحسب على السقف تقديراً.
     */
    public bool $timeout = false;

    public function answer(string $system, string $message): AssistantReply
    {
        if (app()->isProduction()) {
            throw new RuntimeException('The fake AI assistant must not be used in production.');
        }

        $this->requests[] = ['system' => $system, 'message' => $message];

        if ($this->timeout) {
            throw AssistantProviderException::connection('Fake', new ConnectionException('cURL error 28: Operation timed out'));
        }

        if ($this->fail) {
            throw new AssistantProviderException('Fake provider failure.');
        }

        $text = $this->reply ?? (preg_match('/<source id="1"[^>]*>\s*(.{0,200})/su', $message, $match) === 1
            ? __('assistant.fake_reply', ['excerpt' => trim($match[1])]).' [1]'
            : AnswerComposer::NO_ANSWER);

        return new AssistantReply($text, (int) ceil(mb_strlen($system.$message) / 4), (int) ceil(mb_strlen($text) / 4));
    }
}
