<?php

namespace App\Services\BotChallenge;

use App\Contracts\BotChallenge;
use RuntimeException;

/**
 * للتطوير والاختبارات فقط: بلا عنصر في الصفحة، ويقبل (أو يرفض) كما ضُبط، ويحفظ ما فُحص. ويرفض العمل
 * في الإنتاج، فلا يُنسى التحدي الحقيقي.
 */
final class FakeBotChallenge implements BotChallenge
{
    /** @var list<string> */
    public array $checked = [];

    public function __construct(private bool $passes = true, private ?string $siteKey = null) {}

    public function siteKey(): ?string
    {
        return $this->siteKey;
    }

    public function passes(string $token, string $action): bool
    {
        if (app()->isProduction()) {
            throw new RuntimeException('FakeBotChallenge must not be used in production.');
        }

        $this->checked[] = $token;

        return $this->passes;
    }
}
