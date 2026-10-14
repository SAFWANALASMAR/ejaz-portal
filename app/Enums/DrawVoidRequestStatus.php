<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * حالة طلب إلغاء السحب (VoidDraw): معلّق، ثم معتمد أو مرفوض بسبب من مشرف عام غير طالبه، أو ساقط بمضي
 * المدة بلا قرار. ولا رجوع عن القرار (مشغّل في الترحيل).
 */
enum DrawVoidRequestStatus: string implements HasLabel
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Lapsed = 'lapsed';

    public function getLabel(): string
    {
        return __('competitions.draws.void_request_statuses.'.$this->value);
    }
}
