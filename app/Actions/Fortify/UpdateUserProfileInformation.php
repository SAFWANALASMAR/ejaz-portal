<?php

namespace App\Actions\Fortify;

use App\Auth\CurrentPasswordLimiter;
use App\Models\User;
use App\Notifications\ContactDetailsChanged;
use App\Rules\SaudiMobile;
use App\Support\AccountSettings;
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
        // البريد والجوال اختياريان (ملاحظات المدير التنفيذي، 25 أكتوبر 2026)
        $input['email'] = mb_strtolower(trim((string) ($input['email'] ?? ''))) ?: null;
        $phone = is_string($input['phone'] ?? null) && trim($input['phone']) !== '' ? $input['phone'] : null;
        $input['phone'] = $phone === null ? null : (SaudiPhone::normalize($phone) ?? $phone);

        $emailChanged = $input['email'] !== $user->email;
        $phoneChanged = $input['phone'] !== $user->phone;

        // تخمين كلمة المرور الحالية يُقفل مع التأكيد وتغيير كلمة المرور، قبل فحص التجزئة
        if ($emailChanged || $phoneChanged) {
            CurrentPasswordLimiter::ensureNotLocked($user, 'current_password', 'updateProfileInformation');
        }

        // معرّف دخول واحد على الأقل يبقى لمن لا اسم مستخدم له، فلا يُغلق حسابه بإفراغ البريد والجوال (مراجعة المشرف المبكرة، ٢)
        if ($user->username === null && $input['email'] === null && $input['phone'] === null) {
            throw ValidationException::withMessages(['email' => __('account.profile.identifier_required')])->errorBag('updateProfileInformation');
        }

        $validator = Validator::make($input, [
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'email' => ['nullable', 'string', 'email:rfc', 'max:255', User::emailTakenRule($user->id)],
            'phone' => ['nullable', 'string', new SaudiMobile, Rule::unique('users')->ignore($user->id)->whereNotNull('phone_verified_at')],
            'current_password' => $emailChanged || $phoneChanged ? ['required', 'string', 'current_password:web'] : ['nullable'],
        ]);

        // التحقق مرة واحدة، ففحص كلمة المرور الحالية تجزئة واحدة لا اثنتان
        if ($validator->fails()) {
            if ($validator->errors()->has('current_password')) {
                CurrentPasswordLimiter::failed($user);
            }

            throw (new ValidationException($validator))->errorBag('updateProfileInformation');
        }

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

        if (($emailChanged || $phoneChanged) && $previousEmail !== null) {
            Notification::route('mail', $previousEmail)->notify(new ContactDetailsChanged($emailChanged, $phoneChanged));
        }

        if ($emailChanged && $input['email'] !== null) {
            // رابط التوثيق الآلي متى كان التوثيق مفعّلاً من اللوحة، وإلا يطلبه صاحب الحساب من «بياناتي»
            if (AccountSettings::verificationEnabled()) {
                $user->sendEmailVerificationNotification();
            }

            session()->flash('status', __('account.profile.email_changed'));
        } elseif ($phoneChanged) {
            session()->flash('status', __('account.profile.phone_changed'));
        }
    }
}
