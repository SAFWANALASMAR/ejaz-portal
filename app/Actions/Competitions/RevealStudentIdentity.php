<?php

namespace App\Actions\Competitions;

use App\Auth\CurrentPasswordLimiter;
use App\Enums\ActivityEvent;
use App\Models\Competition;
use App\Models\CompetitionSubmission;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * الاطلاع الكامل على رقم هوية الطالب وجوال ولي أمره: لمن يملك التصدير الكامل فيما يدير نتائجه، لا لمنشئ المسابقة
 * تلقائياً (CompetitionPolicy::viewNationalId — قرار صفوان، 27 أكتوبر 2026)، وكل اطلاع يُسجَّل في سجل العمليات
 * بلا الرقم نفسه. وفي غير ذلك يُعرضان مخفيين إلا آخر أربعة أرقام.
 *
 * وقبل أول اطلاع تأكيد كلمة المرور الحالية، يبقى للجلسة مدة قصيرة (CONFIRM_SECONDS) بقفل تأكيد كلمة المرور نفسه
 * (CurrentPasswordLimiter)، وحد لعدد مرات الاطلاع لكل مستخدم في الساعة: فلا تكشف جلسة مسروقة أرقام القاصرين واحداً
 * واحداً بلا كلمة المرور ولا بلا حد (المراجعة المستقلة على v0.14.0).
 */
class RevealStudentIdentity
{
    public const CONFIRM_SECONDS = 600;

    private const SESSION_KEY = 'national_id_reveal.confirmed';

    /**
     * @return array{national_id: string|null, guardian_phone: string|null}
     *
     * @throws AuthorizationException
     * @throws ValidationException بلا تأكيد كلمة المرور، أو فوق حد الساعة
     */
    public function handle(User $actor, CompetitionSubmission $submission): array
    {
        if (! $actor->can('viewNationalId', $submission->competition)) {
            throw new AuthorizationException;
        }

        if (! self::confirmed($actor)) {
            throw ValidationException::withMessages(['password' => __('competitions.student.admin.reveal_confirm_required')]);
        }

        $key = 'national-id-reveal:'.$actor->id;

        if (RateLimiter::tooManyAttempts($key, (int) config('security.national_id_reveals_per_hour'))) {
            throw ValidationException::withMessages(['password' => __('competitions.student.admin.reveal_throttled', [
                'minutes' => (int) ceil(RateLimiter::availableIn($key) / 60),
            ])]);
        }

        RateLimiter::hit($key, 3600);

        activity('competitions')->performedOn($submission)->causedBy($actor)
            ->event(ActivityEvent::NationalIdViewed->value)
            ->withProperties(['competition_id' => $submission->competition_id, 'reference' => $submission->reference])
            ->log(ActivityEvent::NationalIdViewed->value);

        return [
            'national_id' => $submission->national_id,
            'guardian_phone' => $submission->guardian_phone,
        ];
    }

    /**
     * تأكيد كلمة المرور قبل الاطلاع، لصاحب الاطلاع وحده.
     *
     * @throws AuthorizationException
     * @throws ValidationException كلمة مرور خاطئة، أو محاولات كثيرة
     */
    public function confirm(User $actor, Competition $competition, ?string $password): void
    {
        if (! $actor->can('viewNationalId', $competition)) {
            throw new AuthorizationException;
        }

        CurrentPasswordLimiter::ensureNotLocked($actor, 'password');

        if (! Hash::check((string) $password, $actor->password)) {
            CurrentPasswordLimiter::failed($actor);

            throw ValidationException::withMessages(['password' => __('auth.password')]);
        }

        CurrentPasswordLimiter::passed($actor);
        session()->put(self::SESSION_KEY, ['user' => $actor->id, 'at' => now()->getTimestamp()]);
    }

    public static function confirmed(User $actor): bool
    {
        $confirmed = session()->get(self::SESSION_KEY);

        return is_array($confirmed) && ($confirmed['user'] ?? null) === $actor->id
            && now()->getTimestamp() - (int) ($confirmed['at'] ?? 0) < self::CONFIRM_SECONDS;
    }
}
