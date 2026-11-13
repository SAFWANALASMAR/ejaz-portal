<?php

namespace App\Listeners;

use App\Actions\Accounts\SendEmailVerificationCode;
use App\Models\User;
use App\Support\AccountSettings;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * رمز توثيق البريد بعد التسجيل ما دام مفعّلاً من اللوحة (AccountSettings::emailCodeEnabled — decisions/2026-11-13-feature-email-code-toggle.md)، (قرار اجتماع 6 نوفمبر 2026 — decisions/2026-11-06-email-otp.md)، لا رابط التوثيق.
 * يحل محل مستمع لارافيل بالربط في AppServiceProvider، لأن لارافيل يسجّله لحدث التسجيل دائماً. وما يمنع الإرسال (حد) لا
 * يُفشل التسجيل: صاحب الحساب يطلب رمزاً جديداً من صفحة التوثيق.
 */
class SendVerificationWhenRequired extends SendEmailVerificationNotification
{
    public function handle(Registered $event)
    {
        $user = $event->user;

        if (! AccountSettings::emailCodeEnabled() || ! $user instanceof User || $user->email === null || $user->hasVerifiedEmail()) {
            return;
        }

        try {
            app(SendEmailVerificationCode::class)->handle($user);
        } catch (ValidationException) {
            Log::info('Email verification code after registration was not sent (limit).', ['user' => $user->id]);
        }
    }
}
