<?php

namespace App\Contracts;

use App\Services\Messaging\MessagingException;

/**
 * مزود الرسائل النصية: تقنيات أو يونيفونك أو مسجات، يُختار من «قنوات التواصل» في اللوحة أو من .env
 * (ConfiguredSmsGateway — docs/guides/messaging.md)، وفي التطوير يكتب الرسالة في السجل.
 */
interface SmsGateway
{
    /**
     * @param  string  $phone  بالصيغة الدولية +9665XXXXXXXX
     * @return string|null معرّف الرسالة عند المزود إن ردّه
     *
     * @throws MessagingException فشل المزود أو غياب إعداده
     */
    public function send(string $phone, string $message): ?string;
}
