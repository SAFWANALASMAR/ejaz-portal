<?php

namespace App\Services\Sms;

use App\Contracts\SmsGateway;
use App\Services\Messaging\MessagingException;
use App\Services\Messaging\ProviderHttp;

/**
 * يونيفونك (Unifonic): POST https://el.cloud.unifonic.com/rest/SMS/messages بحقول نموذج
 * (AppSid، SenderID، Body، Recipient)، والرقم دولي بلا + ولا 00. النجاح success=true وerrorCode ER-00
 * ومعه data.MessageID، والخطأ success=false برمز مثل ER-04. المرجع: docs.unifonic.com
 * (docs/guides/messaging.md، وفيه ما بقي للتأكيد من المزود).
 */
class UnifonicSmsGateway implements SmsGateway
{
    public function __construct(private string $appSid, private ?string $sender) {}

    public function send(string $phone, string $message): ?string
    {
        $response = ProviderHttp::send(fn () => ProviderHttp::request()
            ->asForm()
            ->post((string) config('messaging.endpoints.unifonic'), array_filter([
                'AppSid' => $this->appSid,
                // اختياري عند المزود: بدونه يُرسل باسم المرسل الافتراضي للحساب
                'SenderID' => $this->sender,
                'Body' => $message,
                'Recipient' => ProviderHttp::digits($phone),
            ], fn ($value) => $value !== null && $value !== '')));

        $success = filter_var($response->json('success'), FILTER_VALIDATE_BOOLEAN);

        if (! $response->successful() || ! $success) {
            $code = $response->json('errorCode');

            throw MessagingException::http($response->status(), is_string($code) && $code !== '' ? 'unifonic:'.$code : null);
        }

        $id = $response->json('data.MessageID');

        return is_scalar($id) ? (string) $id : null;
    }
}
