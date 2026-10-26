<?php

namespace App\Support;

use App\Models\Competition;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

/**
 * حدود الدخول والتسجيل لكل عنوان شبكة أثناء نوافذ مسابقة الطلاب (مراجعة المطابقة): طلاب المدرسة الواحدة خلف عنوان
 * واحد، فيتسع حد العنوان في نافذتين منفصلتين (المراجعة النهائية):
 * - حدود التسجيل (registration_*) ما دام تسجيل مسابقة طلاب منشورة مفتوحاً، فالمدارس تسجّل طلابها فيه.
 * - حدود الدخول (login_*) في أيام الاختبار وحدها: مرحلة جارية، أو تبدأ خلال ساعة فيدخل الطلاب قبلها. لا طوال أسابيع
 *   التسجيل، فلا يتسع ما يحمي من تخمين كلمات المرور أكثر مما يلزم.
 * وخارجهما حدود #64 كما هي. والأرقام في config/competitions.php (window_limits) من .env. والحد لكل حساب وسقف البوابة
 * لا يتغيران.
 */
final class SchoolNetworkLimits
{
    private const CACHE_KEY = 'competition-window:';

    public static function limit(string $key): int
    {
        $active = str_starts_with($key, 'registration_') ? self::registrationOpen() : self::examWindow();

        return $active && config()->has('competitions.student.window_limits.'.$key)
            ? (int) config('competitions.student.window_limits.'.$key)
            : (int) config('security.'.$key);
    }

    public static function registrationOpen(): bool
    {
        return self::remember('registration', fn (Builder $query) => $query->where('opens_at', '<=', now())->where('closes_at', '>', now()));
    }

    public static function examWindow(): bool
    {
        return self::remember('exam', fn (Builder $query) => $query->whereHas('rounds', fn ($query) => $query->where('starts_at', '<=', now()->addHour())->where('ends_at', '>', now())));
    }

    /**
     * عند حفظ مسابقة أو مرحلة، فلا ينتظر الحد دقيقة ليتسع أو يعود.
     */
    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY.'registration');
        Cache::forget(self::CACHE_KEY.'exam');
    }

    /**
     * @param  Closure(Builder<Competition>): mixed  $window
     */
    private static function remember(string $name, Closure $window): bool
    {
        return (bool) Cache::remember(self::CACHE_KEY.$name, 60, function () use ($window): bool {
            $query = Competition::query()->published()->where('student_mode', true);
            $window($query);

            return $query->exists();
        });
    }
}
