<?php

namespace App\Support;

use App\Models\Setting;

/**
 * مدة الاحتفاظ ببيانات الحسابات المحذوفة بالأيام: إعداد واحد من اللوحة (المشرف العام)، تحدده الجمعية.
 * وفارغاً لا يُحذف شيء آلياً (ejaz:purge-anonymized-data). ويُستعمل لكل الوحدات، فلا يُبنى إعدادان.
 */
class Retention
{
    public const MAX_DAYS = 3650;

    public static function days(): ?int
    {
        $days = Setting::group('retention')['days'] ?? null;

        return is_int($days) && $days > 0 ? $days : null;
    }
}
