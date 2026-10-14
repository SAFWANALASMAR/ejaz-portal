<?php

namespace App\Actions\Fortify;

use App\Auth\CurrentPasswordLimiter;
use App\Models\User;
use App\Notifications\ContactDetailsChanged;
use App\Rules\SaudiMobile;
use App\Support\SaudiPhone;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

/**
 * تعديل البيانات الأساسية. تغيير البريد أو الجوال يُسقط توثيقه ويطلب توثيقاً جديداً، ويشترط كلمة المرور
 * الحالية ويُنبَّه به البريد القديم، فلا تستولي جلسة مسروقة على الحساب بتغيير بريده ثم استعادة كلمة مروره.
 */
class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function update(User $user, array $input): void
    {
        $input['email'] = mb_strtolower(trim((string) ($input['email'] ?? '')));
        $input['phone'] = SaudiPhone::normalize(is_string($input['phone'] ?? null) ? $input['phone'] : null) ?? ($input['phone'] ?? null);

        $emailChanged = $input['email'] !== $user->email;
        $phoneChanged = $input['phone'] !== $user->phone;

        // تخمين كلمة المرور الحالية يُقفل مع التأكيد وتغيير كلمة المرور، قبل فحص التجزئة
        if ($emailChanged || $phoneChanged) {
            CurrentPasswordLimiter::ensureNotLocked($user, 'current_password', 'updateProfileInformation');
        }

        $validator = Validator::make($input, [
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone' => ['required', 'string', new SaudiMobile, Rule::unique('users')->ignore($user->id)],
            'current_password' => $emailChanged || $phoneChanged ? ['required', 'string', 'current_password:web'] : ['nullable'],
        ]);

        if ($validator->errors()->has('current_password')) {
            CurrentPasswordLimiter::failed($user);
        }

        $validator->validateWithBag('updateProfileInformation');

        if ($emailChanged || $phoneChanged) {
            CurrentPasswordLimiter::passed($user);
        }

        $previousEmail = $user->email;

        $user->forceFill([
            'name' => trim((string) $input['name']),
            'email' => $input['email'],
            'phone' => $input['phone'],
            'email_verified_at' => $emailChanged ? null : $user->email_verified_at,
            'phone_verified_at' => $phoneChanged ? null : $user->phone_verified_at,
        ])->save();

        if ($emailChanged || $phoneChanged) {
            Notification::route('mail', $previousEmail)->notify(new ContactDetailsChanged($emailChanged, $phoneChanged));
        }

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
            session()->flash('status', __('account.profile.email_changed'));
        } elseif ($phoneChanged) {
            session()->flash('status', __('account.profile.phone_changed'));
        }
    }
}
