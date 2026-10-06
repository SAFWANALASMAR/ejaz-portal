<?php

namespace App\Listeners;

use App\Actions\Accounts\SendPhoneVerificationCode;
use App\Models\User;
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
        $this->log('login_failed', $event->user instanceof User ? $event->user : null, [
            'identifier_hash' => $this->identifierHash($event->credentials),
        ]);
    }

    public function handleLockout(Lockout $event): void
    {
        $this->log('login_lockout', null, [
            'identifier_hash' => $this->identifierHash($event->request->only('login', 'email')),
        ]);
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
     * @param  array<string, mixed>  $properties
     */
    private function log(string $event, ?User $user, array $properties = []): void
    {
        $request = request();

        $logger = activity('security')
            ->event($event)
            ->withProperties([
                'ip' => $request->ip(),
                'agent' => Str::limit((string) $request->userAgent(), 200, ''),
                ...$properties,
            ]);

        if ($user !== null) {
            $logger->causedBy($user)->performedOn($user);
        }

        $logger->log($event);
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    private function identifierHash(array $credentials): ?string
    {
        $identifier = $credentials['login'] ?? $credentials['email'] ?? null;

        return is_string($identifier) && $identifier !== ''
            ? hash('sha256', mb_strtolower(trim($identifier)))
            : null;
    }
}
