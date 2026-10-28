<?php

namespace App\Actions\Users;

use App\Enums\ActivityEvent;
use App\Models\User;
use App\Support\PasswordPolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * كلمة مرور مؤقتة من اللوحة لمن لا يستعيدها بنفسه: لا بريد موثق له ولا جوال موثق (ملاحظات المدير التنفيذي عبر صفوان،
 * 25 أكتوبر 2026). للمدير التنفيذي والمسؤول التقني (UserPolicy::resetPassword).
 *
 * لا يكتبها المنفّذ، فلا تُختار ضعيفة: تُولَّد عشوائية تطابق السياسة، وتُعرض له مرة واحدة ليسلّمها صاحبها، ولا تُحفظ نصاً
 * ولا تدخل السجل. ويُطالَب صاحب الحساب بتغييرها عند دخوله (password_set_by_admin_at — RequirePasswordUpgrade)، وتنتهي
 * جلساته ورمز «تذكّرني»: الجلسات بتغيّر تجزئة كلمة المرور عند طلبها التالي (AuthenticateSession في الموقع واللوحة)
 * أياً كان مخزنها، Redis أو قاعدة البيانات، ويُسجَّل الإجراء بمنفّذه.
 */
class ResetPasswordByAdmin
{
    public const LENGTH = 16;

    /**
     * حروف كلمة المرور المؤقتة: لاتينية كبيرة وصغيرة وأرقام ورموز، بلا ما يُفسَّر في HTML (< > & " ') فلا تُقصّ في
     * الإشعار، وبلا ما يتشابه عند النسخ باليد (0 O 1 l I) — مراجعة المشرف المبكرة، ٣.
     */
    public const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789-_.@#%+=!?*';

    /**
     * @return string كلمة المرور المؤقتة، لتُعرض للمنفّذ مرة واحدة
     *
     * @throws AuthorizationException
     */
    public function handle(User $actor, User $user): string
    {
        if (! $actor->can('resetPassword', $user)) {
            throw new AuthorizationException;
        }

        do {
            $password = self::generate();
        } while (! PasswordPolicy::satisfiedBy($password, $user));

        DB::transaction(function () use ($actor, $user, $password): void {
            $user->forceFill([
                'password' => $password,
                'password_set_by_admin_at' => now(),
                'remember_token' => Str::random(60),
            ])->save();

            activity('security')
                ->causedBy($actor)
                ->performedOn($user)
                ->event(ActivityEvent::PasswordSetByAdmin->value)
                ->log(ActivityEvent::PasswordSetByAdmin->value);
        });

        return $password;
    }

    private static function generate(): string
    {
        $last = strlen(self::ALPHABET) - 1;

        return implode('', array_map(fn (): string => self::ALPHABET[random_int(0, $last)], range(1, self::LENGTH)));
    }
}
