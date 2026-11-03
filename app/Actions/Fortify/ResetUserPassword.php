<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Notifications\PasswordChanged;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

/**
 * تعيين كلمة مرور جديدة بعد الاستعادة، برابط البريد أو برمز الجوال (ResetPasswordByPhone)، بسياسة كلمة المرور وبيانات
 * صاحبها. ويُنبَّه صاحب الحساب بالتغيير (قرار صفوان، 20 أكتوبر 2026). وتنتهي جلساته الأخرى ببصمة كلمة المرور
 * (AuthenticateSession)، ويتجدد رمز «تذكّرني» (CompletePasswordReset في Fortify، وResetPasswordByPhone)، ولا دخول
 * تلقائي بعدها، فيبقى التحقق الثنائي على حسابات اللوحة.
 */
class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    /**
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function reset(User $user, array $input): void
    {
        Validator::make($input, [
            'password' => $this->passwordRules($user->name, $user->email, $user->phone, $user->username),
        ])->validate();

        // كلمة المرور الجديدة تطابق السياسة، فتُرفع مطالبة التغيير (RequirePasswordUpgrade). وتُرفع علامة «يلزمه تعيين كلمة
        // مرور» بعد إعادة ضبط التحقق الثنائي (ResetTwoFactorByAdmin): هذا رابط البريد الموثق، ورمز الجوال يُرفض قبله
        // لحسابها (ResetPasswordByPhone)
        $user->forceFill([
            'password' => Hash::make($input['password']),
            'password_upgrade_required_at' => null,
            'password_set_by_admin_at' => null,
            'must_set_password_at' => null,
        ])->save();

        $user->notify(new PasswordChanged);
    }
}
