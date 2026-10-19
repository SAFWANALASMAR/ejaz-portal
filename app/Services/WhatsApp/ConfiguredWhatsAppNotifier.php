<?php

namespace App\Services\WhatsApp;

use App\Contracts\WhatsAppNotifier;
use App\Services\Messaging\MessagingException;
use App\Services\Messaging\MessagingSettings;
use InvalidArgumentException;

/**
 * يختار المزود عند كل إرسال من الإعداد النافذ (MessagingSettings): cloud بمفاتيحه، أو log في التطوير.
 * والإنتاج بلا مزود مضبوط: MessagingException(not_configured).
 */
class ConfiguredWhatsAppNotifier implements WhatsAppNotifier
{
    public function send(string $phone, WhatsAppMessage $message): ?string
    {
        return $this->resolve()->send($phone, $message);
    }

    public function resolve(): WhatsAppNotifier
    {
        $settings = MessagingSettings::whatsapp();

        if ($settings['provider'] === 'log') {
            if (app()->isProduction()) {
                throw MessagingException::notConfigured();
            }

            return new LogWhatsAppNotifier;
        }

        if ($settings['provider'] !== 'cloud') {
            throw new InvalidArgumentException('Unsupported WhatsApp driver: '.$settings['provider']);
        }

        if (! MessagingSettings::ready('whatsapp')) {
            throw MessagingException::notConfigured();
        }

        return new CloudWhatsAppNotifier(
            (string) $settings['phone_number_id'],
            (string) $settings['access_token'],
            $settings['graph_version'],
            $settings['language'],
            $settings['templates'],
        );
    }
}
