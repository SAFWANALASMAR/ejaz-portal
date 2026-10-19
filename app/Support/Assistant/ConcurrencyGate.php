<?php

namespace App\Support\Assistant;

use Closure;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;

/**
 * حد الطلبات المتزامنة إلى المزود من البوابة كلها: خانات بأقفال الذاكرة المؤقتة (Redis في الإنتاج)، يأخذ الطلب أول
 * خانة فارغة ويحررها بعد الرد. والقفل ينتهي وحده بعد المهلة وهامش، فلا تبقى خانة محجوزة إن مات العامل.
 */
final class ConcurrencyGate
{
    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T|null null إن امتلأت الخانات
     */
    public static function run(Closure $callback): mixed
    {
        $lock = self::acquire();

        if ($lock === null) {
            return null;
        }

        try {
            return $callback();
        } finally {
            $lock->release();
        }
    }

    private static function acquire(): ?Lock
    {
        $seconds = (int) config('assistant.timeout') + 10;

        for ($slot = 1; $slot <= max(1, (int) config('assistant.concurrency')); $slot++) {
            $lock = Cache::lock('assistant.slot.'.$slot, $seconds);

            if ($lock->get()) {
                return $lock;
            }
        }

        return null;
    }
}
