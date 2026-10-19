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
     * ينشئ اجتماع تيمز ويعيد معرّفه ورابط الانضمام، والمنظِّم الذي أُنشئ في تقويمه (فيُحدَّث ويُلغى فيه
     * ولو تغيّر المنظِّم في الإعداد بعده). وإعادة الطلب نفسه لا تنشئ اجتماعاً ثانياً.
     *
     * @return array{id: string, join_url: string, organizer: string|null}
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
