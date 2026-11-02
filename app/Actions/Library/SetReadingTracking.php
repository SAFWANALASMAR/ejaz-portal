<?php

namespace App\Actions\Library;

use App\Models\User;

/**
 * «لا تحفظ قراءاتي» (قرار صفوان، 2 نوفمبر 2026 — docs/decisions/2026-11-01-my-reading.md): صاحب الحساب يوقف حفظ تقدّمه في المكتبة
 * أو يعيده. والإيقاف لا يحذف ما حُفظ قبله، فلحذفه «امسح سجل قراءاتي». بلا سجل عمليات: اختيار صاحب الحساب لنفسه.
 */
class SetReadingTracking
{
    public function handle(User $user, bool $disabled): void
    {
        $user->forceFill(['reading_tracking_disabled_at' => $disabled ? ($user->reading_tracking_disabled_at ?? now()) : null])->saveQuietly();
    }
}
