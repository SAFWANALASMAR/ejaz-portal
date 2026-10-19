<?php

namespace App\Services\Sms;

use App\Contracts\SmsGateway;
use App\Services\Messaging\MessagingException;
use App\Services\Messaging\ProviderHttp;

/**
 * تقنيات (Taqnyat): POST https://api.taqnyat.sa/v1/messages بمفتاح Bearer وجسم JSON
 * {recipients, body, sender}، والرقم دولي بلا + ولا 00. النجاح 201 ومعه messageId، والخطأ برمز HTTP
 * (400 طلب أو رصيد أو اسم مرسل، و401 مفتاح). المرجع: dev.taqnyat.sa (docs/guides/messaging.md).
 */
class TaqnyatSmsGateway implements SmsGateway
{
    public function __construct(private string $apiKey, private string $sender) {}

    public function send(string $phone, string $message): ?string
    {
        $response = ProviderHttp::send(fn () => ProviderHttp::request()
            ->withToken($this->apiKey)
            ->asJson()
            ->post((string) config('messaging.endpoints.taqnyat'), [
                'recipients' => [(int) ProviderHttp::digits($phone)],
                'body' => $message,
                'sender' => $this->sender,
            ]));

        if ($response->status() !== 201) {
            throw MessagingException::http($response->status());
        }

        $id = $response->json('messageId');

        return is_scalar($id) ? (string) $id : null;
    }
}
