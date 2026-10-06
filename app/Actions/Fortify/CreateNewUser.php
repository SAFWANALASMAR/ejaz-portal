<?php

namespace App\Actions\Fortify;

use App\Enums\RoleName;
use App\Models\User;
use App\Rules\SaudiMobile;
use App\Support\NetworkPrefix;
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

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        // حد مرتفع لكل عنوان (config/security.php)، لأن الشبكات المشتركة تجمع آلاف
        // المستخدمين خلف عنوان واحد. والحماية الأساسية هي توثيق الجوال.
        // IPv4 لكل عنوان، وIPv6 لكل مشترك (/64) حتى لا يُتجاوز الحد بتدوير العناوين
        $throttleKey = 'register:'.NetworkPrefix::subscriber(request()->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, (int) config('security.registration_per_ip_per_hour'))) {
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
