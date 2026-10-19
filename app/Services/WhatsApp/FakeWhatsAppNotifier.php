<?php

namespace App\Services\WhatsApp;

use App\Contracts\WhatsAppNotifier;
use RuntimeException;

/**
 * للاختبارات: يحفظ الرسائل في الذاكرة ليُفحص ما أُرسل.
 */
class FakeWhatsAppNotifier implements WhatsAppNotifier
{
    /**
     * @var list<array{phone: string, message: string, usage: string, parameters: list<string>}>
     */
    public array $sent = [];

    public function send(string $phone, WhatsAppMessage $message): ?string
    {
        if (app()->isProduction()) {
            throw new RuntimeException('FakeWhatsAppNotifier must not be used in production.');
        }

        $this->sent[] = ['phone' => $phone, 'message' => $message->text, 'usage' => $message->usage, 'parameters' => $message->parameters];

        return null;
    }
}
