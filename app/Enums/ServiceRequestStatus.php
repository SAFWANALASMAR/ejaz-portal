<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * حالات الطلب الإلكتروني (requirements/02): مسودة ← مُستلم ← قيد المراجعة ← بحاجة لاستكمال
 * ← مقبول أو مرفوض ← مغلق. والانتقالات المسموحة هنا، ويرفض الإجراء ما سواها.
 */
enum ServiceRequestStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Received = 'received';
    case UnderReview = 'under_review';
    case NeedsCompletion = 'needs_completion';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Closed = 'closed';

    public function getLabel(): string
    {
        return __('services.statuses.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft, self::Closed => 'gray',
            self::Received, self::UnderReview => 'info',
            self::NeedsCompletion => 'warning',
            self::Accepted => 'success',
            self::Rejected => 'danger',
        };
    }

    /**
     * المتقدم يعدّل طلبه ويرسله في هاتين الحالتين فقط.
     */
    public function editableByApplicant(): bool
    {
        return $this === self::Draft || $this === self::NeedsCompletion;
    }

    /**
     * الحالات التي ينقل الموظف الطلب إليها من هذه الحالة.
     *
     * @return list<self>
     */
    public function staffTransitions(): array
    {
        return match ($this) {
            self::Received => [self::UnderReview, self::NeedsCompletion, self::Accepted, self::Rejected],
            self::UnderReview => [self::NeedsCompletion, self::Accepted, self::Rejected],
            self::NeedsCompletion => [self::UnderReview, self::Rejected, self::Closed],
            self::Accepted, self::Rejected => [self::Closed],
            self::Draft, self::Closed => [],
        };
    }
}
