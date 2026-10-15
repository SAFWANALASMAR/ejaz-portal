<?php

namespace App\Console\Commands;

use App\Actions\Competitions\LapseDrawVoidRequests as LapseRequests;
use Illuminate\Console\Command;

/**
 * إسقاط طلبات إلغاء السحب التي لم يقرر فيها مشرف عام آخر خلال سبعة أيام (decisions.md — 14 أكتوبر 2026).
 * يعمل يومياً، والطلب لا يسقط مرتين (LapseDrawVoidRequests).
 */
class LapseDrawVoidRequests extends Command
{
    protected $signature = 'ejaz:lapse-draw-void-requests';

    protected $description = 'Lapse draw void requests that no second super admin decided within the allowed days';

    public function handle(LapseRequests $lapse): int
    {
        $this->info('Lapsed '.$lapse->handle().' draw void request(s).');

        return self::SUCCESS;
    }
}
