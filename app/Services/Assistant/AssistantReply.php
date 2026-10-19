<?php

namespace App\Services\Assistant;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * رد المزود: النص وعدد الوحدات (tokens) للتكلفة التقديرية.
 */
final readonly class AssistantReply
{
    public function __construct(
        public string $text,
        public int $inputTokens = 0,
        public int $outputTokens = 0,
    ) {}

    /**
     * من عدد الوحدات الذي أعاده المزود، وإن غاب أو كان صفراً فتقدير بالزيادة من طول النص (حرفان لكل وحدة)، فلا تصير
     * التكلفة صفراً ولا يُتجاوز السقف بمزود لا يعيدها (المراجعة الأمنية، م4).
     */
    public static function measured(string $text, int $inputTokens, int $outputTokens, string $sent): self
    {
        if ($inputTokens <= 0 || ($outputTokens <= 0 && $text !== '')) {
            // تنبيه واحد في اليوم، بلا النص
            if (Cache::add('assistant.usage-missing', true, now()->addDay())) {
                Log::warning('AI assistant provider returned no token usage; the cost is estimated from the text length.');
            }

            $inputTokens = max($inputTokens, (int) ceil(mb_strlen($sent) / 2));
            $outputTokens = max($outputTokens, (int) ceil(mb_strlen($text) / 2));
        }

        return new self($text, $inputTokens, $outputTokens);
    }
}
