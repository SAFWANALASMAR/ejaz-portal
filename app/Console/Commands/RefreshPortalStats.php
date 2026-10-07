<?php

namespace App\Console\Commands;

use App\Support\ContentCache;
use App\Support\PortalStats;
use Illuminate\Console\Command;

/**
 * يحسب إحصائيات البوابة في الخلفية ويخزّنها. وإن تغيّر رقم آلي معروض في الرئيسية مُسحت ذاكرة المحتوى،
 * فيظهر الرقم الجديد.
 */
class RefreshPortalStats extends Command
{
    protected $signature = 'ejaz:refresh-stats';

    protected $description = 'حساب إحصائيات البوابة وتخزينها';

    public function handle(): int
    {
        ['previous' => $previous, 'current' => $current] = PortalStats::refresh();

        $public = array_flip(PortalStats::PUBLIC);

        if ($previous === null || array_intersect_key($previous, $public) !== array_intersect_key($current, $public)) {
            ContentCache::flush();
        }

        $this->info('حُسبت الإحصائيات.');

        return self::SUCCESS;
    }
}
