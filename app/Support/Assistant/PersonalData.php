<?php

namespace App\Support\Assistant;

/**
 * يحذف من السؤال ما يشبه البيانات الشخصية قبل إرساله وحفظه: البريد، وأي سلسلة أرقام من تسعة فأكثر (الجوال
 * ورقم الهوية والحساب البنكي) ولو فصلتها مسافات أو شرطات. فالواجهة تنبّه ألا تُكتب، وهذا حارس بعدها.
 */
final class PersonalData
{
    public static function redact(string $text): string
    {
        $mask = __('assistant.redacted');
        $digits = strtr($text, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']);
        $digits = (string) preg_replace('/[\p{L}\p{N}._%+\-]+@[\p{L}\p{N}.\-]+\.\p{L}{2,}/u', $mask, $digits);

        // الفواصل بين الأرقام: مسافات وشرطات ونقاط وأقواس وشرطة مائلة، كما يكتب الناس الجوال (المراجعة الأمنية، ن3)
        return (string) preg_replace('/\+?\(?\d(?:[\s\-.()\/]{0,3}\d){8,}\)?/u', $mask, $digits);
    }
}
