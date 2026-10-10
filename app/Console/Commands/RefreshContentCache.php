<?php

namespace App\Console\Commands;

use App\Support\ContentCache;
use Illuminate\Console\Command;

/**
 * يمسح ذاكرة المحتوى العام وحدها بعد كل نشر، لأن القوالب والمسارات الجديدة تغيّر ما يُعرض
 * (بطاقة قسم بُني للتو مثلاً). بديل cache:clear الذي يصفّر حدود الحماية.
 */
class RefreshContentCache extends Command
{
    protected $signature = 'ejaz:refresh-content';

    protected $description = 'مسح ذاكرة المحتوى العام بعد النشر، دون المساس بحدود الحماية';

    public function handle(): int
    {
        ContentCache::flush();

        $this->info('مُسحت ذاكرة المحتوى العام.');

        return self::SUCCESS;
    }
}
