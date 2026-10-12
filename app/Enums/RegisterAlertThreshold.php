<?php

namespace App\Enums;

/**
 * عتبات تنبيه المستند: دخول مدة التنبيه، والانتهاء. يُرسل لكل عتبة وتاريخ انتهاء تنبيه واحد.
 */
enum RegisterAlertThreshold: string
{
    case Expiring = 'expiring';
    case Expired = 'expired';
}
