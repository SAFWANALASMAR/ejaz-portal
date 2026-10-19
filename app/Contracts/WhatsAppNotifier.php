<?php

namespace App\Contracts;

use App\Services\Messaging\MessagingException;
use App\Services\WhatsApp\WhatsAppMessage;

/**
 * مزود رسائل واتساب: WhatsApp Cloud API من Meta برسائل قوالب معتمدة، يُضبط من «قنوات التواصل» في اللوحة
 * أو من .env (ConfiguredWhatsAppNotifier — docs/guides/messaging.md). وفي التطوير تُكتب الرسالة في السجل،
 * وفي الاختبارات تُحفظ في الذاكرة.
 */
interface WhatsAppNotifier
{
    /**
     * @param  string  $phone  بالصيغة الدولية +9665XXXXXXXX
     * @return string|null معرّف الرسالة عند المزود إن ردّه
     *
     * @throws MessagingException فشل المزود أو غياب إعداده أو قالب الاستعمال
     */
    public function send(string $phone, WhatsAppMessage $message): ?string;
}
