<?php

namespace App\Actions\Fortify;

use App\Auth\CurrentPasswordLimiter;
use App\Http\Middleware\RequirePasswordUpgrade;
use App\Models\User;
use App\Notifications\PasswordChanged;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;

/**
 * تغيير كلمة المرور من الحساب: بكلمة المرور الحالية، وتخمينها يُقفل مع التأكيد (CurrentPasswordLimiter)،
 * ويُنبَّه صاحب الحساب بالتغيير.
 */
class UpdateUserPassword implements UpdatesUserPasswords
{
    use PasswordValidationRules;

    /**
     * Validate and update the user's password.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function update(User $user, array $input): void
    {
        CurrentPasswordLimiter::ensureNotLocked($user, 'current_password', 'updatePassword');

        $validator = Validator::make($input, [
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => $this->passwordRules($user->name, $user->email, $user->phone),
        ], [
            'current_password.current_password' => __('The provided password does not match your current password.'),
        ]);

        // التحقق مرة واحدة، ففحص كلمة المرور الحالية تجزئة واحدة لا اثنتان
        if ($validator->fails()) {
            if ($validator->errors()->has('current_password')) {
                CurrentPasswordLimiter::failed($user);
            }

            throw (new ValidationException($validator))->errorBag('updatePassword');
        }

        CurrentPasswordLimiter::passed($user);

        $user->forceFill([
            'password' => Hash::make($input['password']),
        ])->save();

        // وكلمة المرور الجديدة تطابق السياسة، فتُرفع مطالبة التغيير (RequirePasswordUpgrade)
        if (request()->hasSession()) {
            request()->session()->forget(RequirePasswordUpgrade::SESSION_KEY);
        }

        $user->notify(new PasswordChanged);
    }
}
