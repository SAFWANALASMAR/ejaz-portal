<?php

namespace App\Support;

/**
 * زر واتساب الجمعية في التذييل وصفحة «تواصل معنا» (البيانات الرسمية، 26 أكتوبر 2026): رقم جوال سعودي من «إعدادات الموقع ←
 * التواصل» (contact.whatsapp)، ومنه رابط wa.me بالصيغة الدولية بلا +. ورقم لا يصح لا رابط له.
 */
final class WhatsApp
{
    public static function link(?string $number): ?string
    {
        $phone = SaudiPhone::normalize($number);

        return $phone === null ? null : 'https://wa.me/'.ltrim($phone, '+');
    }
}
