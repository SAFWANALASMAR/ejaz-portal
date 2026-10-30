<?php

namespace App\Support;

/**
 * إخفاء البيانات الشخصية في بطاقة «بياناتي» (تدقيق المشرف على التصور النهائي): يُعرض المخفي أولاً، ويكشف صاحب الحساب
 * القيمة الكاملة بزر «إظهار البيانات» في متصفحه وحده. والقيمة الكاملة لا تُرسل إلا لصاحبها (صفحته هو، وهي في نموذج
 * التعديل تحتها أصلاً)، فالإخفاء ستر عن النظر لا حماية من الخادم.
 */
final class Masked
{
    /**
     * البريد: أول حرف من الاسم ثم نقاط ثم النطاق كاملاً (s••••@example.com).
     */
    public static function email(?string $email): ?string
    {
        if ($email === null || ! str_contains($email, '@')) {
            return $email;
        }

        [$local, $domain] = explode('@', $email, 2);

        return mb_substr($local, 0, 1).'••••@'.$domain;
    }

    /**
     * الجوال بصيغة العرض وآخر رقمين ظاهرين (SaudiPhone::masked).
     */
    public static function phone(?string $phone): ?string
    {
        return $phone === null ? null : SaudiPhone::masked($phone);
    }
}
