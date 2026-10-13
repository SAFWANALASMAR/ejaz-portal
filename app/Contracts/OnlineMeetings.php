<?php

namespace App\Contracts;

use App\Models\Meeting;

/**
 * اجتماعات تيمز لاجتماعات الجمعية العمومية (requirements/04): Microsoft Graph في الإنتاج، وتطبيق وهمي
 * في التطوير والاختبارات (docs/decisions.md). ولا يُرسل إليها إلا عنوان الاجتماع وموعده.
 */
interface OnlineMeetings
{
    /**
     * ينشئ اجتماع تيمز ويعيد معرّفه ورابط الانضمام.
     *
     * @return array{id: string, join_url: string}
     */
    public function create(Meeting $meeting): array;

    /**
     * يحدّث العنوان والموعد.
     */
    public function update(Meeting $meeting): void;

    /**
     * يلغيه، والملغى أو المحذوف أصلاً نجاح.
     */
    public function cancel(Meeting $meeting): void;
}
