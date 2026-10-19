<?php

namespace App\Services\Sms;

use App\Contracts\SmsGateway;
use App\Services\Messaging\MessagingException;
use App\Services\Messaging\MessagingSettings;
use InvalidArgumentException;

/**
 * يختار المزود عند كل إرسال من الإعداد النافذ (MessagingSettings)، فيسري تغيير اللوحة على عمال الطابور
 * دون إعادة تشغيلهم. والإنتاج بلا مزود حقيقي مضبوط: MessagingException(not_configured)، ولا يُستعمل log فيه.
 */
class ConfiguredSmsGateway implements SmsGateway
{
    public function send(string $phone, string $message): ?string
    {
        return $this->resolve()->send($phone, $message);
    }

    public function resolve(): SmsGateway
    {
        $settings = MessagingSettings::sms();

        if ($settings['provider'] === 'log') {
            if (app()->isProduction()) {
                throw MessagingException::notConfigured();
            }

            return new LogSmsGateway;
        }

        if (! in_array($settings['provider'], ['taqnyat', 'unifonic', 'msegat'], true)) {
            throw new InvalidArgumentException('Unsupported SMS driver: '.$settings['provider']);
        }

        if (! MessagingSettings::ready('sms')) {
            throw MessagingException::notConfigured();
        }

        $key = (string) $settings['api_key'];

        return match ($settings['provider']) {
            'taqnyat' => new TaqnyatSmsGateway($key, (string) $settings['sender']),
            'unifonic' => new UnifonicSmsGateway($key, $settings['sender']),
            'msegat' => new MsegatSmsGateway((string) $settings['username'], $key, (string) $settings['sender']),
        };
    }
}
