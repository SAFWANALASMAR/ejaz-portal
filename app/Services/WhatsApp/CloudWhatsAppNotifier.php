<?php

namespace App\Services\WhatsApp;

use App\Contracts\WhatsAppNotifier;
use App\Services\Messaging\MessagingException;
use App\Services\Messaging\ProviderHttp;

/**
 * WhatsApp Cloud API من Meta: POST https://graph.facebook.com/{version}/{phone-number-id}/messages بمفتاح
 * Bearer، ورسالة قالب معتمد (type=template) بمتغيرات نصه بترتيبها. فالرسالة التي تبدأها الجمعية لا تكون
 * إلا بقالب معتمد عند Meta. النجاح 200 ومعه messages[0].id، والخطأ error.code. (docs/guides/messaging.md)
 */
class CloudWhatsAppNotifier implements WhatsAppNotifier
{
    /**
     * @param  array<string, string>  $templates  اسم القالب المعتمد لكل استعمال
     */
    public function __construct(
        private string $phoneNumberId,
        private string $accessToken,
        private string $graphVersion,
        private string $language,
        private array $templates,
    ) {}

    public function send(string $phone, WhatsAppMessage $message): ?string
    {
        $template = $message->template ?? ($this->templates[$message->usage] ?? null);

        if ($template === null || $template === '') {
            throw new MessagingException('no_template');
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => '+'.ProviderHttp::digits($phone),
            'type' => 'template',
            'template' => [
                'name' => $template,
                'language' => ['code' => $this->language],
            ],
        ];

        if ($message->parameters !== []) {
            $payload['template']['components'] = [[
                'type' => 'body',
                'parameters' => array_map(fn (string $text): array => ['type' => 'text', 'text' => $text], $message->parameters),
            ]];
        }

        $url = rtrim((string) config('messaging.endpoints.whatsapp'), '/').'/'.rawurlencode($this->graphVersion).'/'.rawurlencode($this->phoneNumberId).'/messages';

        $response = ProviderHttp::send(fn () => ProviderHttp::request()
            ->withToken($this->accessToken)
            ->asJson()
            ->post($url, $payload));

        if (! $response->successful()) {
            $code = $response->json('error.code');

            throw MessagingException::http($response->status(), is_scalar($code) ? 'meta:'.$code : null);
        }

        $id = $response->json('messages.0.id');

        return is_string($id) ? $id : null;
    }
}
