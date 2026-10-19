<?php

namespace Tests\Fakes;

use App\Contracts\SmsGateway;

/**
 * مزود رسائل وهمي للاختبارات: يحفظ الرسائل بدل إرسالها.
 */
class FakeSmsGateway implements SmsGateway
{
    /** @var list<array{phone: string, message: string}> */
    public array $sent = [];

    public function send(string $phone, string $message): ?string
    {
        $this->sent[] = ['phone' => $phone, 'message' => $message];

        return null;
    }

    public function lastCodeFor(string $phone): ?string
    {
        foreach (array_reverse($this->sent) as $sms) {
            if ($sms['phone'] === $phone && preg_match('/\b(\d{6})\b/', $sms['message'], $match)) {
                return $match[1];
            }
        }

        return null;
    }

    public static function swap(): self
    {
        $fake = new self;
        app()->instance(SmsGateway::class, $fake);

        return $fake;
    }
}
