<?php

namespace App\Console\Commands;

use Filament\Actions\Exports\Models\Export;
use Illuminate\Console\Command;

/**
 * يحذف ملفات التصدير وسجلاتها بعد يوم: فيها بيانات شخصية (أسماء وبريد وجوال وإجابات)، فلا تُحفظ أرشيفاً
 * (الحد الأدنى في نظام حماية البيانات الشخصية — decisions.md). وتشمل المصدِّرين الثلاثة: الطلبات
 * (ServiceRequestExporter)، والمسجلين في البرامج (ProgramRegistrationExporter)، ومشاركات المسابقات
 * (SubmissionExporter). ويُعاد التصدير عند الحاجة.
 */
class PruneExports extends Command
{
    protected $signature = 'ejaz:prune-exports {--hours=24 : عمر الملف بالساعات}';

    protected $description = 'حذف ملفات التصدير بعد يوم من إنشائها';

    public function handle(): int
    {
        $count = 0;

        Export::query()
            ->where('created_at', '<', now()->subHours(max(1, (int) $this->option('hours'))))
            ->lazyById()
            ->each(function (Export $export) use (&$count): void {
                $export->deleteFileDirectory();
                $export->delete();
                $count++;
            });

        $this->info("حُذف {$count} تصدير.");

        return self::SUCCESS;
    }
}
