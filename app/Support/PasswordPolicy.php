<?php

namespace App\Support;

use App\Models\User;
use App\Rules\LatinMixedCase;
use App\Rules\PasswordWithoutPersonalData;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * سياسة كلمة المرور (قرار صفوان، 20 أكتوبر 2026 — docs/plans/auth-hardening.md): 12 حرفاً على الأقل، وحرف من أي لغة،
 * ورقم، ورمز، وحرف لاتيني كبير وآخر صغير متى كان فيها لاتيني، ولم تظهر في تسريب، ولا تحوي بيانات صاحبها.
 * تعريف واحد للتسجيل والتغيير والاستعادة وأمر إنشاء المشرف، ولفحص كلمة المرور القائمة عند الدخول.
 */
final class PasswordPolicy
{
    public const MIN_LENGTH = 12;

    /**
     * القواعد البنيوية وحدها، بلا فحص التسريب (طلب خارجي).
     */
    public static function structure(): Password
    {
        return Password::min(self::MIN_LENGTH)->letters()->numbers()->symbols()->rules([new LatinMixedCase]);
    }

    /**
     * Password::defaults(): البنيوية مع فحص التسريب.
     */
    public static function defaults(): Password
    {
        return self::structure()->uncompromised();
    }

    /**
     * قواعد حقل كلمة مرور جديدة، ببيانات صاحبها.
     *
     * @return list<mixed>
     */
    public static function rules(?string $name, ?string $email, ?string $phone, bool $confirmed = true): array
    {
        $rules = ['required', 'string', Password::default(), new PasswordWithoutPersonalData($name, $email, $phone)];

        return $confirmed ? [...$rules, 'confirmed'] : $rules;
    }

    /**
     * كلمة المرور القائمة عند الدخول: تطابق القواعد البنيوية وقاعدة البيانات الشخصية؟ بلا فحص التسريب، فلا طلب خارجي
     * في كل دخول. وما لم يطابق يُطالَب صاحبه بتغييره (RequirePasswordUpgrade).
     */
    public static function satisfiedBy(string $password, User $user): bool
    {
        return Validator::make(['password' => $password], [
            'password' => [self::structure(), new PasswordWithoutPersonalData($user->name, $user->email, $user->phone)],
        ])->passes();
    }
}
