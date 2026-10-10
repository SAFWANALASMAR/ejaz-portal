<?php

namespace App\Services\Sms;

use App\Contracts\SmsGateway;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * للتطوير فقط: يكتب الرسالة في سجل التطبيق بدل إرسالها.
 * الرمز يظهر في: docker compose logs -f app  أو storage/logs/laravel.log
 */
class LogSmsGateway implements SmsGateway
{
    public function send(string $phone, string $message): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('LogSmsGateway must not be used in production.');
        }

        Log::info('[SMS] '.$phone.': '.$message);
    }
}
