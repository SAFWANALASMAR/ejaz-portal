<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * حالة مقطع المشاركة: يُرفع مجزأً، ثم يُعالج في الخلفية، فيصير جاهزاً أو مرفوضاً بسببه.
 */
enum VideoStatus: string implements HasLabel
{
    case Uploading = 'uploading';
    case Processing = 'processing';
    case Ready = 'ready';
    case Rejected = 'rejected';

    public function getLabel(): string
    {
        return __('competitions.video_statuses.'.$this->value);
    }
}
