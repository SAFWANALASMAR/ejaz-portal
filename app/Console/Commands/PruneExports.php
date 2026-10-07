<?php

namespace App\Console\Commands;

use Filament\Actions\Exports\Models\Export;
use Illuminate\Console\Command;

/**
 * ملفات التصدير تحمل بيانات شخصية (أسماء وبريد وجوال وإجابات)، فلا تبقى في القرص الخاص بعد
 * يوم من إنشائها: يُحذف الملف وسجله. ويُعاد التصدير عند الحاجة.
 */
class PruneExports extends Command
{
    protected $signature = 'ejaz:prune-exports {--hours=24 : عمر الملف بالساعات}';

    protected $description = 'Delete Excel exports and their files older than the given age';

    public function handle(): int
    {
        $count = 0;

        Export::query()
            ->where('created_at', '<', now()->subHours(max(1, (int) $this->option('hours'))))
            ->each(function (Export $export) use (&$count): void {
                $export->deleteFileDirectory();
                $export->delete();
                $count++;
            });

        $this->info((string) $count);

        return self::SUCCESS;
    }
}
