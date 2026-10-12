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
     * @var list<array{phone: string, message: string}>
     */
    public array $sent = [];

    public function send(string $phone, string $message): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('FakeWhatsAppNotifier must not be used in production.');
        }

        $this->sent[] = ['phone' => $phone, 'message' => $message];
    }
}
