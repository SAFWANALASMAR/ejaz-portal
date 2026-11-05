<?php

namespace App\Listeners;

use App\Actions\Accounts\NoticeNewDeviceLogin;
use App\Actions\Accounts\SendPhoneVerificationCode;
use App\Actions\Accounts\WarnRepeatedTwoFactorFailures;
use App\Enums\ActivityEvent;
use App\Models\User;
use App\Support\AccountSettings;
use App\Support\LoginIdentifier;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;

/**
 * سجل أمني لأحداث الدخول في سجل العمليات باسم 'security'.
 * لا تُسجَّل كلمات المرور ولا المعرّف المكتوب نصاً، بل بصمته فقط.
 *
 * المحاولة الفاشلة على حساب موجود تُربط به حساباً معنياً لا منفّذاً (قد يحاول غير
 * صاحبه)، فيجدها إخفاء البيانات ولو تغيّرت البصمة. ولا يُكتب عنوان ولا متصفح على
 * حساب أُخفيت بياناته، فلا يعود إليه أثر يدل على صاحبه.
 */
class RecordAuthenticationEvents
{
    public function handleLogin(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $loggedInBefore = $event->user->last_login_at !== null;
        $event->user->forceFill(['last_login_at' => now()])->saveQuietly();

        $this->log(ActivityEvent::Login, $event->user);

        app(NoticeNewDeviceLogin::class)->handle($event->user, $loggedInBefore);
    }

    public function handleFailed(Failed $event): void
    {
        $identifier = $this->identifier($event->credentials);
        // مع authenticateUsing يطلق Fortify الحدث بلا مستخدم دائماً، فيُبحث عن الحساب بالمعرّف:
        // استعلام واحد سواء وُجد الحساب أم لا، فلا يختلف الزمن
        $account = User::findByLoginIdentifier($identifier);

        $this->log(ActivityEvent::LoginFailed, $account, [
            'identifier_hash' => LoginIdentifier::hash($identifier),
        ], byAccountOwner: false);
    }

    public function handleLockout(Lockout $event): void
    {
        $identifier = $this->identifier($event->request->only('login', 'email'));

        $this->log(ActivityEvent::LoginLockout, User::findByLoginIdentifier($identifier), [
            'identifier_hash' => LoginIdentifier::hash($identifier),
        ], byAccountOwner: false);
    }

    /**
     * رمز خاطئ بعد كلمة مرور صحيحة: قد تكون كلمة المرور مسرّبة، فيُسجَّل على الحساب ولا يُنسب إلى صاحبه.
     */
    public function handleTwoFactorFailed(TwoFactorAuthenticationFailed $event): void
    {
        $this->log(ActivityEvent::TwoFactorFailed, $event->user, byAccountOwner: false);

        app(WarnRepeatedTwoFactorFailures::class)->handle($event->user);
    }

    public function handleLogout(Logout $event): void
    {
        if ($event->user instanceof User) {
            $this->log(ActivityEvent::Logout, $event->user);
        }
    }

    public function handlePasswordReset(PasswordReset $event): void
    {
        if ($event->user instanceof User) {
            $this->log(ActivityEvent::PasswordReset, $event->user);
        }
    }

    /**
     * رمز توثيق الجوال يُرسل مباشرة بعد التسجيل متى كان التوثيق مفعّلاً من اللوحة وللحساب جوال («ما ودنا نربطها برقم الجوال
     * يصير يجيه رابط للتوثيق، خليها فيما بعد» — المدير التنفيذي عبر صفوان، 25 أكتوبر 2026).
     */
    public function handleRegistered(Registered $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $this->log(ActivityEvent::Registered, $event->user);

        if (! AccountSettings::verificationEnabled() || $event->user->phoneToVerify() === null) {
            return;
        }

        try {
            app(SendPhoneVerificationCode::class)->handle($event->user);
        } catch (ValidationException) {
            // لا يمنع التسجيل؛ يطلب المستخدم الرمز من صفحة التوثيق
        }
    }

    /**
     * @param  User|null  $user  الحساب المعني بالحدث
     * @param  array<string, mixed>  $properties
     * @param  bool  $byAccountOwner  الحدث من صاحب الحساب نفسه، لا محاولة دخول عليه
     */
    private function log(ActivityEvent $event, ?User $user, array $properties = [], bool $byAccountOwner = true): void
    {
        $request = request();

        // المتصفح يرسله بلا قيد: بترميز غير صالح كان ترميز السجل يرمي استثناءً قبل أن يحسب Fortify المحاولة الفاشلة،
        // فيسقط قفل الدخول وسجله (اختبار الأمن قبل الرفع، مراجعة #61). فيُنقّى قبل الحفظ
        $origin = $user?->anonymized_at === null
            ? ['ip' => $request->ip(), 'agent' => mb_scrub(Str::limit((string) $request->userAgent(), 200, ''), 'UTF-8')]
            : [];

        $logger = activity('security')
            ->event($event->value)
            ->withProperties([...$origin, ...$properties]);

        if ($user !== null) {
            $logger->performedOn($user);

            if ($byAccountOwner) {
                $logger->causedBy($user);
            }
        }

        $logger->log($event->value);
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    private function identifier(array $credentials): ?string
    {
        $identifier = $credentials['login'] ?? $credentials['email'] ?? null;

        return is_string($identifier) ? $identifier : null;
    }
}
