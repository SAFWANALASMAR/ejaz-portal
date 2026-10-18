<?php

namespace App\Support;

use App\Models\DailyVisit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * عدّاد الزيارات البسيط داخل البوابة (قرار صفوان — docs/plans/admin-stats.md): لكل يوم بتوقيت الرياض المشاهدات
 * والزوار الفريدون في الذاكرة المؤقتة، وينقلهما ejaz:refresh-stats إلى daily_visits.
 *
 * - الزائر الفريد ببصمة HMAC بمفتاح التطبيق من عنوانه ومتصفحه وتاريخ اليوم، فلا يُخزَّن عنوان ولا متصفح ولا معرّف
 *   دائم، ولا تُربط بصمة يوم بيوم آخر. والبصمة مفتاح ينتهي بنهاية يومها، لأنها لا تُقرأ بعده، والقاعدة لا تحفظ
 *   إلا المجاميع.
 * - عدّاد تقريبي يعدّ المتصفح لا الإنسان: بلا ملفات تعريف ارتباط، ولا تحليلات، ولا جهة خارجية. ولا يقاوم التضخيم
 *   المتعمد: من يغيّر وكيل متصفحه أو عنوانه يُعدّ زائراً جديداً.
 */
final class Visits
{
    /**
     * عدّادا اليوم يبقيان يومين، فيقرأ آخرَ أرقامه أولُ نقل بعد منتصف الليل.
     */
    private const TTL = 2 * 24 * 60 * 60;

    public static function today(): string
    {
        return now(config('app.display_timezone'))->toDateString();
    }

    /**
     * مشاهدة صفحة، وزائر جديد إن لم تُرَ بصمته في يومها.
     */
    public static function record(Request $request): void
    {
        $now = now(config('app.display_timezone'));
        $date = $now->toDateString();

        self::increment($date, 'views');

        $fingerprint = hash_hmac('sha256', $request->ip().'|'.$request->userAgent().'|'.$date, (string) config('app.key'));

        // بهامش دقائق: مدة تقرب من الصفر في آخر لحظة من اليوم لا يحفظ معها Cache::add شيئاً، فلا يُعدّ الزائر
        if (Cache::add(self::key($date, 'visitor:'.$fingerprint), true, $now->copy()->endOfDay()->addMinutes(5))) {
            self::increment($date, 'visitors');
        }
    }

    /**
     * ينقل مجموع أمس واليوم إلى daily_visits، تحديثاً لا تكراراً. والمحفوظ لا ينقص: ذاكرة أُخليت (سياسة الإخلاء
     * في performance.md) فبدأ عدّها من الصفر لا تمحو ما نُقل قبلها.
     */
    public static function persist(): void
    {
        $today = now(config('app.display_timezone'));

        foreach ([$today->copy()->subDay()->toDateString(), $today->toDateString()] as $date) {
            $views = Cache::get(self::key($date, 'views'));
            $visitors = Cache::get(self::key($date, 'visitors'));

            if ($views === null && $visitors === null) {
                continue;
            }

            DailyVisit::query()->upsert(
                [['date' => $date, 'views' => (int) $views, 'visitors' => (int) $visitors]],
                ['date'],
                [
                    'views' => DB::raw('greatest(daily_visits.views, excluded.views)'),
                    'visitors' => DB::raw('greatest(daily_visits.visitors, excluded.visitors)'),
                ],
            );
        }
    }

    private static function increment(string $date, string $counter): void
    {
        // يُنشأ بمدته أول مرة في يومه، والزيادة الذرية بعدها لا تمس المدة
        Cache::add(self::key($date, $counter), 0, self::TTL);
        Cache::increment(self::key($date, $counter));
    }

    private static function key(string $date, string $name): string
    {
        return 'visits:'.$date.':'.$name;
    }
}
