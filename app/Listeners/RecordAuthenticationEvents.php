<?php

namespace App\Listeners;

use App\Actions\Accounts\SendPhoneVerificationCode;
use App\Models\User;
use App\Support\LoginIdentifier;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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

        $event->user->forceFill(['last_login_at' => now()])->saveQuietly();

        $this->log('login', $event->user);
    }

    public function handleFailed(Failed $event): void
    {
        $identifier = $this->identifier($event->credentials);
        $account = $event->user instanceof User ? $event->user : User::findByLoginIdentifier($identifier);

        $this->log('login_failed', $account, [
            'identifier_hash' => LoginIdentifier::hash($identifier),
        ], byAccountOwner: false);
    }

    public function handleLockout(Lockout $event): void
    {
        $identifier = $this->identifier($event->request->only('login', 'email'));

        $this->log('login_lockout', User::findByLoginIdentifier($identifier), [
            'identifier_hash' => LoginIdentifier::hash($identifier),
        ], byAccountOwner: false);
    }

    public function handleLogout(Logout $event): void
    {
        if ($event->user instanceof User) {
            $this->log('logout', $event->user);
        }
    }

    public function handlePasswordReset(PasswordReset $event): void
    {
        if ($event->user instanceof User) {
            $this->log('password_reset', $event->user);
        }
    }

    /**
     * رمز توثيق الجوال يُرسل مباشرة بعد التسجيل.
     */
    public function handleRegistered(Registered $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $this->log('registered', $event->user);

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
    private function log(string $event, ?User $user, array $properties = [], bool $byAccountOwner = true): void
    {
        $request = request();

        $origin = $user?->anonymized_at === null
            ? ['ip' => $request->ip(), 'agent' => Str::limit((string) $request->userAgent(), 200, '')]
            : [];

        $logger = activity('security')
            ->event($event)
            ->withProperties([...$origin, ...$properties]);

        if ($user !== null) {
            $logger->performedOn($user);

            if ($byAccountOwner) {
                $logger->causedBy($user);
            }
        }

        $logger->log($event);
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
