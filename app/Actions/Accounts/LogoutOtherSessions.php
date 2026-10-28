<?php

namespace App\Actions\Accounts;

use App\Auth\CurrentPasswordLimiter;
use App\Enums\ActivityEvent;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * الخروج من كل الأجهزة الأخرى بكلمة المرور الحالية (ASVS 3.3.4 — مطابقة المعايير، 20 أكتوبر 2026). تُعاد تجزئة كلمة
 * المرور، فتنتهي الجلسات الأخرى ببصمتها (AuthenticateSession)، ويتجدد رمز «تذكّرني» فلا تعود بكعكته. والجلسة الحالية
 * باقية. وتخمين كلمة المرور يُقفل مع التأكيد وتغيير كلمة المرور (CurrentPasswordLimiter).
 */
class LogoutOtherSessions
{
    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(User $user, array $input): void
    {
        CurrentPasswordLimiter::ensureNotLocked($user, 'current_password', 'logoutOtherSessions');

        $validator = Validator::make($input, [
            'current_password' => ['required', 'string', 'current_password:web'],
        ], [
            'current_password.current_password' => __('The provided password does not match your current password.'),
        ]);

        if ($validator->fails()) {
            CurrentPasswordLimiter::failed($user);

            throw (new ValidationException($validator))->errorBag('logoutOtherSessions');
        }

        CurrentPasswordLimiter::passed($user);

        Auth::guard('web')->logoutOtherDevices((string) $input['current_password']);

        $user->setRememberToken(Str::random(60));
        $user->save();

        activity('security')->performedOn($user)->causedBy($user)
            ->event(ActivityEvent::OtherSessionsEnded->value)->log(ActivityEvent::OtherSessionsEnded->value);
    }
}
