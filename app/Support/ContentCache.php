<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * ذاكرة مؤقتة لما يُعرض من المحتوى العام: مقاطع الرئيسية الآن، ثم صفحات الأقسام في بنودها.
 * تُخزَّن نصوصاً (HTML) لا كائنات، تحت وسم واحد يمسحه كل تعديل في الإعدادات أو المحتوى
 * بعد اعتماد المعاملة، فيظهر التعديل فوراً. والنشر يمسحها أيضاً (php artisan optimize:clear)،
 * لأن القوالب والمسارات الجديدة تغيّر ما يُعرض.
 */
final class ContentCache
{
    public const TAG = 'content';

    /**
     * @param  Closure(): string  $render
     */
    public static function remember(string $key, Closure $render): string
    {
        return Cache::tags(self::TAG)->remember($key, now()->addHour(), $render);
    }

    public static function flush(): void
    {
        DB::afterCommit(fn () => Cache::tags(self::TAG)->flush());
    }
}
