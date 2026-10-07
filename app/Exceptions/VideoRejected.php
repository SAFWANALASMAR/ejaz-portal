<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * المقطع المستلم مرفوض قبل فحصه (حجم لا يطابق المعلن مثلاً)، بسبب من أسباب الرفض في
 * lang/ar/competitions.php (video.reasons). ترميه VideoUploads::open()، ويرفض به ProcessSubmissionVideo المقطع.
 */
class VideoRejected extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct('Video rejected: '.$reason);
    }
}
