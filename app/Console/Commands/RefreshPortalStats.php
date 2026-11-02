<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Support\ContentCache;
use App\Support\HomeContent;
use App\Support\HomePortalStats;
use App\Support\PortalStats;
use App\Support\Visits;
use Illuminate\Console\Command;
use Throwable;

/**
 * ينقل عدّاد الزيارات إلى القاعدة، ثم يحسب إحصائيات البوابة في الخلفية ويخزّنها. وإن تغيّر رقم آلي تستعمله
 * خانة في الرئيسية مُسحت ذاكرة الرئيسية وحدها، فيظهر الرقم الجديد ولا تفقد الصفحات الأخرى تخزينها. وما لا
 * تستعمله الرئيسية لا يمسح شيئاً.
 */
class RefreshPortalStats extends Command
{
    protected $signature = 'ejaz:refresh-stats';

    protected $description = 'حساب إحصائيات البوابة وتخزينها';

    public function handle(): int
    {
        // قبل الحساب، فأرقام زيارات اليوم في الإحصائيات هي المنقولة في هذا التشغيل. وتعذّر النقل لا يوقف الحساب
        try {
            Visits::persist();
        } catch (Throwable $exception) {
            report($exception);
        }

        ['previous' => $previous, 'current' => $current] = PortalStats::refresh();

        // المسح لما تستعمله الرئيسية فعلاً وحده، فلا تفقد الصفحات العامة تخزينها دورياً وقت الذروة
        $used = array_flip(array_intersect(PortalStats::PUBLIC, collect((array) (Setting::group('home')['stats'] ?? []))
            ->pluck('source')
            ->filter(fn ($source): bool => is_string($source))
            ->all()));

        // و«البوابة بالأرقام» (HomePortalStats) متى ظهرت وتغيّر رقمها أو رسمها: تُعاد الرئيسية وحدها مرة في التشغيل على الأكثر
        $portal = HomePortalStats::refresh() && HomeContent::showsPortalStats();

        if ($portal || ($used !== [] && ($previous === null || array_intersect_key($previous, $used) !== array_intersect_key($current, $used)))) {
            // مقطع الإحصائيات جزء من ذاكرة الرئيسية (HomeController)
            ContentCache::forget('home');
        }

        $this->info('حُسبت الإحصائيات.');

        return self::SUCCESS;
    }
}
