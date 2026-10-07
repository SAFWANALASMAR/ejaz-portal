<?php

namespace App\Services\BotChallenge;

use App\Contracts\BotChallenge;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Cloudflare Turnstile: المتصفح يحل التحدي ويرسل رمزه مع النموذج، والخادم يتحقق منه هنا. ولا يُرسل
 * عنوان الزائر من الخادم (الحقل اختياري)، تقليلاً لما يُنقل خارج المملكة. وأي تعذر رفض.
 */
final class TurnstileChallenge implements BotChallenge
{
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function __construct(private string $siteKey, private string $secretKey) {}

    public function siteKey(): string
    {
        return $this->siteKey;
    }

    public function passes(string $token): bool
    {
        if ($token === '' || strlen($token) > 2048) {
            return false;
        }

        try {
            $response = Http::asForm()->timeout(5)->post(self::VERIFY_URL, [
                'secret' => $this->secretKey,
                'response' => $token,
            ]);
        } catch (ConnectionException) {
            return false;
        }

        return $response->successful() && $response->json('success') === true;
    }
}
