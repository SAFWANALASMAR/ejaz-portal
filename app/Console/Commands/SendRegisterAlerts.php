<?php

namespace App\Console\Commands;

use App\Actions\Registers\SendRegisterAlerts as SendAlerts;
use Illuminate\Console\Command;

/**
 * تنبيه المسؤولين بالمستندات القريبة من الانتهاء والمنتهية (requirements/13 §1). يعمل يومياً، ولا يكرر
 * تنبيه عتبة لتاريخ انتهاء واحد (SendRegisterAlerts).
 */
class SendRegisterAlerts extends Command
{
    protected $signature = 'registers:send-alerts';

    protected $description = 'Alert responsible staff of register entries entering their alert window or expiring';

    public function handle(SendAlerts $alerts): int
    {
        $this->info('Sent '.$alerts->handle().' alert(s).');

        return self::SUCCESS;
    }
}
