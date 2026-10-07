<?php

namespace App\Console\Commands;

use Filament\Actions\Exports\Models\Export;
use Illuminate\Console\Command;

/**
 * يحذف ملفات التصدير وسجلاتها بعد يوم: فيها بيانات المتقدمين الشخصية، فلا تُحفظ أرشيفاً (الحد الأدنى
 * في نظام حماية البيانات الشخصية — decisions.md).
 */
class PruneExports extends Command
{
    protected $signature = 'ejaz:prune-exports';

    protected $description = 'حذف ملفات التصدير بعد يوم من إنشائها';

    public const HOURS = 24;

    public function handle(): int
    {
        $count = 0;

        Export::query()->where('created_at', '<', now()->subHours(self::HOURS))->lazyById()->each(function (Export $export) use (&$count): void {
            $export->deleteFileDirectory();
            $export->delete();
            $count++;
        });

        $this->info("حُذف {$count} تصدير.");

        return self::SUCCESS;
    }
}
