<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\DB;

/**
 * اسم المستخدم للدخول (ملاحظات المدير التنفيذي عبر صفوان، 25 أكتوبر 2026): من 3 إلى 30 من الأحرف اللاتينية الصغيرة
 * والأرقام والنقطة والشرطة السفلية، يبدأ بحرف. فلا يشبه البريد (بلا @) ولا رقم الجوال (يبدأ بحرف)، فيُعرف نوع معرّف
 * الدخول من شكله (LoginIdentifier). ويُوحَّد بأحرف صغيرة قبل الحفظ والبحث.
 *
 * ولا يُقبل اسم محجوز يوهم بأنه حساب رسمي، ولا اسم حساب مجهَّل، برسالة «غير متاح» نفسها (مراجعة المشرف المبكرة، ١٧).
 */
final class Username
{
    public const MIN = 3;

    public const MAX = 30;

    public const PATTERN = '/^[a-z][a-z0-9._]{2,29}$/';

    /**
     * أسماء توهم بأنها للجمعية أو لإدارة البوابة. ويُحجز معها كل اسم يبدأ بـ ejaz.
     */
    public const RESERVED = [
        'admin', 'administrator', 'root', 'system', 'sysadmin', 'superadmin', 'moderator', 'staff', 'official',
        'info', 'support', 'help', 'contact', 'team', 'security', 'webmaster', 'postmaster', 'noreply', 'no_reply',
    ];

    public static function normalize(?string $username): string
    {
        return mb_strtolower(trim(Digits::latin((string) $username)));
    }

    /**
     * @return list<mixed>
     */
    public static function rules(): array
    {
        return ['required', 'string', 'min:'.self::MIN, 'max:'.self::MAX, 'regex:'.self::PATTERN,
            function (string $attribute, mixed $value, Closure $fail): void {
                $username = self::normalize(is_string($value) ? $value : '');

                if (self::isReserved($username) || self::isRetired($username)) {
                    $fail(__('account.register.username_taken'));
                }
            },
        ];
    }

    public static function isReserved(string $username): bool
    {
        return in_array($username, self::RESERVED, true) || str_starts_with($username, 'ejaz');
    }

    /**
     * يُحفظ اسم الحساب المجهَّل بصمةً، فلا يأخذه غيره (AnonymizeUser).
     */
    public static function retire(string $username): void
    {
        DB::table('retired_usernames')->insertOrIgnore(['hash' => self::hash($username), 'created_at' => now()]);
    }

    public static function isRetired(string $username): bool
    {
        return DB::table('retired_usernames')->where('hash', self::hash($username))->exists();
    }

    private static function hash(string $username): string
    {
        return hash_hmac('sha256', self::normalize($username), (string) config('app.key'));
    }
}
