<?php

namespace App\Contracts;

/**
 * مزود رسائل واتساب. المزود الفعلي ينتظر قرار صفوان (docs/open-questions.md)، وفي التطوير تُكتب
 * الرسالة في السجل، وفي الاختبارات تُحفظ في الذاكرة.
 */
interface WhatsAppNotifier
{
    /**
     * @param  string  $phone  بالصيغة الدولية +9665XXXXXXXX
     */
    public function send(string $phone, string $message): void;
}
