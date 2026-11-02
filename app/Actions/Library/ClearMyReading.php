<?php

namespace App\Actions\Library;

use App\Models\LibraryProgress;
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
        return LibraryProgress::query()->whereBelongsTo($user)->delete();
    }
}
