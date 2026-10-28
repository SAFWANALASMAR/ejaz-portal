<?php

namespace App\Actions\Accounts;

use App\Models\User;
use App\Notifications\NewDeviceLogin;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * تنبيه صاحب الحساب بالبريد عند دخوله من متصفح لم يدخل منه قبل (قرار صفوان، 20 أكتوبر 2026). والمتصفح المعروف
 * بكعكة طويلة الأجل (مشفّرة كبقية الكعكات) تحمل أرقام الحسابات التي دخلت منه، فلا جدول ولا بصمة متصفح. وأول دخول
 * للحساب بعد التسجيل لا يُنبَّه به، فهو المتصفح الذي سجّل منه.
 */
class NoticeNewDeviceLogin
{
    public const COOKIE = 'ejaz_known_logins';

    private const KEEP = 10;

    public function handle(User $user, bool $loggedInBefore): void
    {
        $known = $this->known();
        $id = (string) $user->getKey();

        if (in_array($id, $known, true)) {
            return;
        }

        // الوكيل منقّى ومقصور كسجل الأمن: بترميز غير صالح كان ترميز مهمة البريد في الطابور يرمي استثناءً، فدخول «تذكّرني»
        // من طلب GET يرد 500 في كل صفحة ما دام المتصفح يرسله (مراجعة المشرف على #64)
        if ($loggedInBefore) {
            $user->notify(new NewDeviceLogin(now(), mb_scrub(Str::limit((string) request()->userAgent(), 200, ''), 'UTF-8')));
        }

        $known = array_slice([...$known, $id], -self::KEEP);
        Cookie::queue(Cookie::make(self::COOKIE, implode('.', $known), 60 * 24 * 400, null, null, null, true, false, 'lax'));
    }

    /**
     * @return list<string>
     */
    private function known(): array
    {
        $value = request()->cookie(self::COOKIE);

        if (! is_string($value) || preg_match('/^\d+(\.\d+)*$/', $value) !== 1) {
            return [];
        }

        return explode('.', $value);
    }
}
