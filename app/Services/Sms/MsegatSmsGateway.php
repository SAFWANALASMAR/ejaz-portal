<?php

namespace App\Services\Sms;

use App\Contracts\SmsGateway;
use App\Services\Messaging\MessagingException;
use App\Services\Messaging\ProviderHttp;

/**
 * مسجات (Msegat): POST https://www.msegat.com/gw/sendsms.php بجسم JSON
 * {userName, apiKey, numbers, userSender, msg, msgEncoding}، والرقم دولي بلا + ولا 00. النجاح code
 * بقيمة 1 أو M0000، وما سواهما خطأ برمزه (M0002 بيانات دخول خاطئة...). ولا معرّف رسالة في الرد
 * الأساسي. المرجع: msegat.docs.apiary.io (docs/guides/messaging.md).
 */
class MsegatSmsGateway implements SmsGateway
{
    private const SUCCESS = ['1', 'M0000'];

    public function __construct(private string $username, private string $apiKey, private string $sender) {}

    public function send(string $phone, string $message): ?string
    {
        $response = ProviderHttp::send(fn () => ProviderHttp::request()
            ->asJson()
            ->post((string) config('messaging.endpoints.msegat'), [
                'userName' => $this->username,
                'apiKey' => $this->apiKey,
                'numbers' => ProviderHttp::digits($phone),
                'userSender' => $this->sender,
                'msg' => $message,
                'msgEncoding' => 'UTF8',
            ]));

        $code = $response->json('code');
        $code = is_scalar($code) ? (string) $code : '';

        if (! $response->successful() || ! in_array($code, self::SUCCESS, true)) {
            throw MessagingException::http($response->status(), $code !== '' ? 'msegat:'.$code : null);
        }

        return null;
    }
}
