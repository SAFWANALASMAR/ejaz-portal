<?php

namespace App\Console\Commands;

use App\Enums\StatsAnalysisStatus;
use App\Jobs\GenerateStatsAnalysis;
use App\Models\StatsAnalysis;
use Illuminate\Console\Command;

/**
 * تقرير التحليل المعلّق بعد مهلته «تعذّر» (مات العامل أو توقف الطابور). كان عرض الصفحة يكتبه عند كل عرض واستطلاع،
 * فصار مجدولاً، والصفحة تقرأ وحدها (مراجعة ما بعد v0.12.0، ملاحظة مهمة ٢).
 */
class FailStaleAnalyses extends Command
{
    protected $signature = 'ejaz:fail-stale-analyses';

    protected $description = 'Mark the stats analyses stuck in the queue past their deadline as failed';

    public function handle(): int
    {
        $failed = StatsAnalysis::query()
            ->where('status', StatsAnalysisStatus::Queued->value)
            ->where('created_at', '<', now()->subMinutes(GenerateStatsAnalysis::STALE_MINUTES))
            ->update(['status' => StatsAnalysisStatus::Failed->value]);

        $this->info((string) $failed);

        return self::SUCCESS;
    }
}
