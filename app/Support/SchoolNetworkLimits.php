<?php

namespace App\Support;

use App\Models\Competition;
use Illuminate\Support\Facades\Cache;

/**
 * حدود الدخول والتسجيل لكل عنوان شبكة أثناء نافذة مسابقة الطلاب (مراجعة المطابقة): طلاب المدرسة الواحدة خلف عنوان
 * واحد، فيتسع حد العنوان ما دام تسجيل مسابقة طلاب منشورة مفتوحاً، أو مرحلة منها جارية (أو تبدأ خلال ساعة، فيدخل
 * الطلاب قبلها). وخارج ذلك حدود #64 كما هي. والأرقام في config/competitions.php (window_limits) من .env.
 * والحد لكل حساب ولكل جهاز وسقف البوابة لا تتغير، فلا يتسع ما يحمي من التخمين على حساب واحد.
 */
final class SchoolNetworkLimits
{
    private const CACHE_KEY = 'competition-window-active';

    public static function limit(string $key): int
    {
        return self::windowActive() && config()->has('competitions.student.window_limits.'.$key)
            ? (int) config('competitions.student.window_limits.'.$key)
            : (int) config('security.'.$key);
    }

    public static function windowActive(): bool
    {
        return (bool) Cache::remember(self::CACHE_KEY, 60, fn (): bool => Competition::query()
            ->published()
            ->where('student_mode', true)
            ->where(fn ($query) => $query
                ->where(fn ($query) => $query->where('opens_at', '<=', now())->where('closes_at', '>', now()))
                ->orWhereHas('rounds', fn ($query) => $query->where('starts_at', '<=', now()->addHour())->where('ends_at', '>', now())))
            ->exists());
    }

    /**
     * عند حفظ مسابقة أو مرحلة، فلا ينتظر الحد دقيقة ليتسع أو يعود.
     */
    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
