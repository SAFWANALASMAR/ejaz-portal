<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * ذاكرة مؤقتة لما يُعرض من المحتوى العام: مقاطع الرئيسية الآن، ثم صفحات الأقسام في بنودها.
 * تُخزَّن نصوصاً (HTML) لا كائنات، تحت رقم إصدار واحد يغيّره كل تعديل في الإعدادات أو المحتوى
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
        return Cache::remember('content.'.self::version().'.'.$key.self::variant(), now()->addHour(), $render);
    }

    /**
     * نسخة الإدارة بعلامات 【ناقص】 حين تُخفى عن الزوار (HIDE_MISSING_MARKERS — MissingText::visible)، فلا تختلط بنسخة الزائر.
     * وتشمل المفاتيح التي ليست HTML (قوائم المعرّفات) فتتكرر لمن يدخل اللوحة وحده، وهذا أبسط من تمييزها والكلفة ضئيلة.
     */
    private static function variant(): string
    {
        return config('app.hide_missing_markers') && MissingText::visible() ? '.marked' : '';
    }

    /**
     * يمسح مفتاحاً واحداً من الإصدار الحالي (الرئيسية عند تغيّر إحصائياتها الآلية مثلاً)، فلا تفقد
     * الصفحات الأخرى تخزينها.
     */
    public static function forget(string $key): void
    {
        DB::afterCommit(function () use ($key): void {
            Cache::forget('content.'.self::version().'.'.$key);
            Cache::forget('content.'.self::version().'.'.$key.'.marked');
        });
    }

    /**
     * إصدار جديد عشوائي بعد اعتماد المعاملة، فلا يكتب حفظان متزامنان الرقم نفسه.
     */
    public static function flush(): void
    {
        DB::afterCommit(fn () => Cache::forever(self::VERSION_KEY, Str::random(16)));
    }

    /**
     * وإن أُخلي مفتاح الإصدار من الذاكرة بُدئ إصدار جديد، فلا يعود محتوى قديم.
     */
    private static function version(): string
    {
        return (string) Cache::rememberForever(self::VERSION_KEY, fn (): string => Str::random(16));
    }
}
