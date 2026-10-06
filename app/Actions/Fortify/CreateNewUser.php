<?php

namespace App\Actions\Fortify;

use App\Enums\RoleName;
use App\Models\User;
use App\Rules\SaudiMobile;
use App\Support\SaudiPhone;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

/**
 * تسجيل حساب جديد للمستفيد. كل حساب جديد يأخذ الدور member فقط.
 */
class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /** حد التسجيل من عنوان واحد في الساعة، للحد من الحسابات الآلية وإغراق الرسائل */
    public const MAX_PER_HOUR = 5;

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        $throttleKey = 'register:'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_PER_HOUR)) {
            throw ValidationException::withMessages(['email' => __('account.register.too_many')]);
        }

        $input['email'] = mb_strtolower(trim((string) ($input['email'] ?? '')));
        $input['phone'] = SaudiPhone::normalize(is_string($input['phone'] ?? null) ? $input['phone'] : null) ?? ($input['phone'] ?? null);

        Validator::make($input, [
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', Rule::unique(User::class)],
            'phone' => ['required', 'string', new SaudiMobile, Rule::unique(User::class)],
            'password' => $this->passwordRules(),
            'privacy' => ['accepted'],
        ])->validate();

        RateLimiter::hit($throttleKey, 3600);

        $user = User::create([
            'name' => trim((string) $input['name']),
            'email' => $input['email'],
            'phone' => $input['phone'],
            'password' => $input['password'],
            'privacy_accepted_at' => now(),
        ]);

        $user->assignRole(RoleName::Member->value);

        return $user;
    }
}
