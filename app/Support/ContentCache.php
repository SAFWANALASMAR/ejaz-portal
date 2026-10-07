<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * ذاكرة مؤقتة لما يُعرض من المحتوى العام: مقاطع الرئيسية الآن، ثم صفحات الأقسام في بنودها.
 * تُخزَّن نصوصاً (HTML) لا كائنات، تحت رقم إصدار واحد يرفعه كل تعديل في الإعدادات أو المحتوى
 * بعد اعتماد المعاملة، فيظهر التعديل فوراً، ويتبخر المخزَّن القديم بمدته. ويعمل على أي مخزن
 * للذاكرة، لا على ما يدعم الوسوم وحده.
 *
 * والنشر يمسحها وحدها (php artisan ejaz:refresh-content)، لأن القوالب والمسارات الجديدة تغيّر
 * ما يُعرض. ولا يُستعمل cache:clear في الإنتاج، لأنه يصفّر حدود الدخول والرسائل ورموز التحقق.
 *
 * الروابط المطلقة في المخزَّن من APP_URL (URL::forceRootUrl في AppServiceProvider)، فلا يدخله
 * شيء من الطلب نفسه.
 */
final class ContentCache
{
    private const VERSION_KEY = 'content.version';

    /**
     * المخزَّن نص أو مصفوفة نصوص، لا كائنات (cache.serializable_classes).
     *
     * @template TValue
     *
     * @param  Closure(): TValue  $render
     * @return TValue
     */
    public static function remember(string $key, Closure $render): mixed
    {
        return Cache::remember('content.'.self::version().'.'.$key, now()->addHour(), $render);
    }

    public static function flush(): void
    {
        DB::afterCommit(fn () => Cache::forever(self::VERSION_KEY, self::version() + 1));
    }

    private static function version(): int
    {
        return (int) Cache::get(self::VERSION_KEY, 1);
    }
}
