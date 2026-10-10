<?php

namespace App\Contracts;

/**
 * مزود الرسائل النصية. المزود الفعلي يُحدد بعد تعاقد الجمعية
 * (docs/open-questions.md)، وفي التطوير يكتب الرسالة في السجل.
 */
interface SmsGateway
{
    /**
     * @param  string  $phone  بالصيغة الدولية +9665XXXXXXXX
     */
    public function send(string $phone, string $message): void;
}
