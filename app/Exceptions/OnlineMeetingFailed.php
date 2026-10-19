<?php

namespace App\Exceptions;

use App\Enums\OnlineMeetingFailure;
use RuntimeException;

/**
 * تعذر ربط تيمز بسببه. رسالتها السبب ورمز HTTP وحدهما، بلا رد Microsoft ولا الاستثناء الأصلي، فلا يصل
 * إلى السجلات ولا إلى failed_jobs إلا ما يُعرض للمسؤول.
 */
class OnlineMeetingFailed extends RuntimeException
{
    /**
     * @param  bool  $nothingCreated  رُفض طلب الإنشاء نفسه رفضاً نهائياً، فلا حدث في التقويم يُسترد
     */
    public function __construct(public readonly OnlineMeetingFailure $reason, public readonly ?int $status = null, public readonly bool $nothingCreated = false)
    {
        parent::__construct('Teams meeting sync failed: '.$reason->value.($status === null ? '' : ' (HTTP '.$status.')'));
    }
}
