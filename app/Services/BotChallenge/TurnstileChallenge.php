<?php

namespace App\Services\BotChallenge;

use App\Contracts\BotChallenge;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Cloudflare Turnstile: المتصفح يحل التحدي ويرسل رمزه مع النموذج، والخادم يتحقق منه هنا. ولا يُرسل
 * عنوان الزائر من الخادم (الحقل اختياري)، تقليلاً لما يُنقل خارج المملكة. وأي تعذر رفض.
 */
final class TurnstileChallenge implements BotChallenge
{
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    /**
     * @param  string|null  $hostname  مضيف البوابة، فلا يُقبل رمز حُل على موقع آخر بالمفتاح نفسه
     */
    public function __construct(private string $siteKey, private string $secretKey, private ?string $hostname = null) {}

    /**
     * Turnstile مضبوط بمفتاحيه، فكل نموذج دخول وطلب استعادة يحل تحدياً قبل المتحكم (VerifyBotChallenge).
     */
    public static function configured(): bool
    {
        return config('services.bot_challenge.driver') === 'turnstile'
            && (string) config('services.bot_challenge.turnstile_site_key') !== ''
            && (string) config('services.bot_challenge.turnstile_secret_key') !== '';
    }

    /**
     * حد المحاولات لكل عنوان أو حساب (قرار صفوان، 26 أكتوبر 2026): مع Turnstile مضبوطاً يصير بلوغه تحدياً لا حجباً، فمن
     * يحل التحدي يتابع حتى سقف أعلى (BOT_CHALLENGE_CEILING_FACTOR ضعفاً) يعود عنده الحجب. وبلا مفتاحيه يبقى الحد كما هو.
     */
    public static function ceiling(int $max): int
    {
        return self::configured() ? $max * max(1, (int) config('security.bot_challenge_ceiling_factor')) : $max;
    }

    public function siteKey(): string
    {
        return $this->siteKey;
    }

    public function passes(string $token, string $action): bool
    {
        if ($token === '' || strlen($token) > 2048) {
            return false;
        }

        // الرمز لمرة واحدة: Cloudflare يرفض المعاد (timeout-or-duplicate)، ويُرفض هنا أيضاً قبل سؤاله، فلا يُقبل الرمز
        // الواحد لطلبين متزامنين يصلان قبل أن يسجّله (اختبار الأمن قبل الرفع، مراجعة #61). وعمر الرمز عند Cloudflare 300 ثانية
        if (! Cache::add('turnstile:'.hash('sha256', $token), true, 300)) {
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

        return $response->successful()
            && $response->json('success') === true
            && $response->json('action') === $action
            && ($this->hostname === null || $response->json('hostname') === $this->hostname);
    }
}
