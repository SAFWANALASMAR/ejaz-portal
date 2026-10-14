<?php

namespace App\Actions\Fortify;

use App\Auth\CurrentPasswordLimiter;
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
            'password' => $this->passwordRules(),
        ], [
            'current_password.current_password' => __('The provided password does not match your current password.'),
        ]);

        if ($validator->errors()->has('current_password')) {
            CurrentPasswordLimiter::failed($user);
        }

        $validator->validateWithBag('updatePassword');

        CurrentPasswordLimiter::passed($user);

        $user->forceFill([
            'password' => Hash::make($input['password']),
        ])->save();

        $user->notify(new PasswordChanged);
    }
}
