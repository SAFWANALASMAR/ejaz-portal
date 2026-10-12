<?php

namespace App\Actions\Registers;

use App\Enums\RegisterAlertThreshold;
use App\Enums\RegisterEntryStatus;
use App\Models\RegisterEntry;
use App\Notifications\RegisterEntryAlert;
use App\Support\Registers;
use Illuminate\Support\Facades\DB;

/**
 * تنبيه المسؤول عن المستند قبل انتهائه وعند انتهائه (requirements/13 §1)، من الأمر اليومي
 * registers:send-alerts.
 *
 * - العتبة: «قارب» متى دخل مدة التنبيه، و«منتهٍ» بعد آخر يوم صالح. والمستند الذي يُرى أول مرة منتهياً
 *   يأخذ تنبيه الانتهاء وحده.
 * - مرة واحدة: صف لكل مستند وعتبة وتاريخ انتهاء بقيد فريد، يُدرج بـ insertOrIgnore ولا يُرسل إلا إن
 *   أُدرج. فلا يتكرر بتشغيل ثانٍ ولا بتشغيلين متزامنين.
 * - التجديد يغيّر تاريخ الانتهاء فيخرج المستند من التنبيه، وتبدأ عتبات التاريخ الجديد عند حلولها.
 * - للمسؤول وحده متى كان فعّالاً ويملك الاطلاع على السجلات.
 */
class SendRegisterAlerts
{
    /**
     * @return int عدد التنبيهات المرسلة
     */
    public function handle(): int
    {
        $today = Registers::today();
        $sent = 0;

        RegisterEntry::query()
            ->whereNotNull('responsible_id')
            ->whereNotNull('expires_on')
            ->whereRaw('(expires_on - alert_days) <= ?::date', [$today->toDateString()])
            ->with('responsible')
            ->chunkById(200, function ($entries) use ($today, &$sent): void {
                foreach ($entries as $entry) {
                    /** @var RegisterEntry $entry */
                    $user = $entry->responsible;

                    if ($user === null || ! $user->is_active || $user->anonymized_at !== null || ! $user->can('view', $entry)) {
                        continue;
                    }

                    $threshold = $entry->status($today) === RegisterEntryStatus::Expired ? RegisterAlertThreshold::Expired : RegisterAlertThreshold::Expiring;

                    $inserted = DB::table('register_entry_alerts')->insertOrIgnore([
                        'register_entry_id' => $entry->id,
                        'threshold' => $threshold->value,
                        'expires_on' => $entry->expires_on?->toDateString(),
                        'user_id' => $user->id,
                        'created_at' => now(),
                    ]);

                    if ($inserted === 1) {
                        $user->notify(new RegisterEntryAlert($entry, $threshold));
                        $sent++;
                    }
                }
            });

        return $sent;
    }
}
