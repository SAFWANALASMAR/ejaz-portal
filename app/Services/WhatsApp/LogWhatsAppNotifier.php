<?php

namespace App\Services\WhatsApp;

use App\Contracts\WhatsAppNotifier;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * للتطوير فقط: يكتب الرسالة في سجل التطبيق بدل إرسالها، والجوال مخفي أوله فلا يُجمع في السجل كاملاً.
 */
class LogWhatsAppNotifier implements WhatsAppNotifier
{
    public function send(string $phone, string $message): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('LogWhatsAppNotifier must not be used in production.');
        }

        Log::info('[WhatsApp] ***'.substr($phone, -4).': '.$message);
    }
}
