<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * نوع رسالة «الاستفسارات والاقتراحات»، من اسم الخدمة نفسه. والأنواع المعتمدة سؤال للجمعية.
 */
enum InquiryType: string implements HasLabel
{
    case Inquiry = 'inquiry';
    case Suggestion = 'suggestion';

    public function getLabel(): string
    {
        return __('inquiries.types.'.$this->value);
    }
}
