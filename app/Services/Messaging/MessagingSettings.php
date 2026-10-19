<?php

namespace App\Services\Messaging;

use App\Models\MessagingChannel;

/**
 * الإعداد النافذ لكل قناة: المضبوط من «قنوات التواصل» في اللوحة متى اختير فيها مزود، وإلا ما في .env
 * (config/services.php). لا خلط بين المصدرين في قناة واحدة، فيُعرف دائماً من أين جاء المفتاح.
 */
final class MessagingSettings
{
    /**
     * @return array{provider: string, source: 'panel'|'env', sender: ?string, username: ?string, api_key: ?string}
     */
    public static function sms(): array
    {
        $channel = MessagingChannel::for(MessagingChannel::SMS);

        if ($channel->provider !== null) {
            return [
                'provider' => $channel->provider,
                'source' => 'panel',
                'sender' => self::string($channel->option('sender')),
                'username' => self::string($channel->option('username')),
                'api_key' => $channel->secret('api_key'),
            ];
        }

        return [
            'provider' => (string) config('services.sms.driver'),
            'source' => 'env',
            'sender' => self::string(config('services.sms.sender')),
            'username' => self::string(config('services.sms.username')),
            'api_key' => self::string(config('services.sms.api_key')),
        ];
    }

    /**
     * @return array{provider: string, source: 'panel'|'env', phone_number_id: ?string, graph_version: string, language: string, templates: array<string, string>, access_token: ?string, app_secret: ?string, verify_token: ?string}
     */
    public static function whatsapp(): array
    {
        $channel = MessagingChannel::for(MessagingChannel::WHATSAPP);
        $panel = $channel->provider !== null;

        $value = fn (string $option, string $config): ?string => self::string($panel ? $channel->option($option) : config('services.whatsapp.'.$config));
        $secret = fn (string $key): ?string => $panel ? $channel->secret($key) : self::string(config('services.whatsapp.'.$key));

        $templates = (array) ($panel ? $channel->option('templates', []) : config('services.whatsapp.templates', []));

        return [
            'provider' => $panel ? (string) $channel->provider : (string) config('services.whatsapp.driver'),
            'source' => $panel ? 'panel' : 'env',
            'phone_number_id' => $value('phone_number_id', 'phone_number_id'),
            'graph_version' => $value('graph_version', 'graph_version') ?? 'v23.0',
            'language' => $value('language', 'language') ?? 'ar',
            'templates' => array_filter(array_map(fn ($name) => self::string($name), $templates)),
            'access_token' => $secret('access_token'),
            'app_secret' => $secret('app_secret'),
            'verify_token' => $secret('verify_token'),
        ];
    }

    /**
     * هل الاستعمال مفعّل؟ المضبوط من اللوحة إن ضُبط، وإلا مفتاحه في .env (config/messaging.php: usages).
     */
    public static function usageEnabled(string $channel, string $usage): bool
    {
        $stored = MessagingChannel::for($channel)->option('usages.'.$usage);

        if (is_bool($stored)) {
            return $stored;
        }

        $key = config('messaging.usages.'.$channel.'.'.$usage);

        return is_string($key) && (bool) config($key);
    }

    /**
     * هل للقناة مزود حقيقي بمفاتيحه؟ (log وfake ليسا مزودين: الأول للتطوير ويرفض الإنتاج)
     */
    public static function ready(string $channel): bool
    {
        if ($channel === MessagingChannel::SMS) {
            $sms = self::sms();

            return in_array($sms['provider'], ['taqnyat', 'unifonic', 'msegat'], true)
                && $sms['api_key'] !== null
                && ($sms['provider'] !== 'msegat' || ($sms['username'] !== null && $sms['sender'] !== null))
                && ($sms['provider'] !== 'taqnyat' || $sms['sender'] !== null);
        }

        $whatsapp = self::whatsapp();

        return $whatsapp['provider'] === 'cloud' && $whatsapp['phone_number_id'] !== null && $whatsapp['access_token'] !== null;
    }

    private static function string(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
