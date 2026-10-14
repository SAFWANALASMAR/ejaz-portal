<?php

namespace App\Auth;

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * قفل تخمين كلمة المرور الحالية من جلسة قائمة: تأكيد كلمة المرور، وتغييرها، وتغيير البريد أو الجوال.
 * عدّاد واحد لكل حساب على المسارات الثلاثة، فلا يتضاعف التخمين بتوزيعه عليها. وخمس محاولات خاطئة
 * تقفلها ربع ساعة كقفل الدخول، والقفل يُفحص قبل التجزئة فلا تُنهك بها المعالجات.
 */
final class CurrentPasswordLimiter
{
    private const int MAX_ATTEMPTS = 5;

    private const int DECAY_SECONDS = 900;

    /**
     * @throws ValidationException
     */
    public static function ensureNotLocked(User $user, string $field, ?string $errorBag = null): void
    {
        $key = self::key($user);

        if (! RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return;
        }

        $exception = ValidationException::withMessages([
            $field => __('account.confirm_password.throttle', ['minutes' => (int) ceil(RateLimiter::availableIn($key) / 60)]),
        ]);

        throw $errorBag === null ? $exception : $exception->errorBag($errorBag);
    }

    public static function failed(User $user): void
    {
        RateLimiter::hit(self::key($user), self::DECAY_SECONDS);
    }

    public static function passed(User $user): void
    {
        RateLimiter::clear(self::key($user));
    }

    private static function key(User $user): string
    {
        return 'confirm-password:'.$user->id;
    }
}
