<?php

namespace App\Actions\Library;

use App\Models\LibraryProgress;
use App\Models\ProgramRegistration;
use App\Models\User;

/**
 * «امسح سجل قراءاتي»: يحذف صاحب الحساب تقدّمه كله في المكتبة، ولا يمس غيره (docs/decisions/2026-11-01-my-reading.md).
 * بلا سجل عمليات، فالسجل نفسه بيانات صاحبه، وحذفه بطلبه.
 */
class ClearMyReading
{
    /**
     * @return int عدد ما حُذف
     */
    public function handle(User $user): int
    {
        // وتقدّمه في برامج المكتبة التي لم يُتمّها يعود إلى الصفر معه (والمُتمّ يبقى بشهادته — docs/decisions/2026-11-02-reading-program.md)
        ProgramRegistration::query()->whereBelongsTo($user)->whereNull('completed_at')->whereNotNull('started_at')
            ->update(['pages_seen' => null, 'pages_count' => 0, 'opened_at' => null, 'seconds_baseline' => 0]);

        return LibraryProgress::query()->whereBelongsTo($user)->delete();
    }
}
