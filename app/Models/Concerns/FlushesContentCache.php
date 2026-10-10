<?php

namespace App\Models\Concerns;

use App\Support\ContentCache;

/**
 * نموذج يُعرض في الموقع العام: حفظه وحذفه يمسحان ذاكرة المحتوى العام بعد اعتماد المعاملة،
 * فيظهر التعديل فوراً. كل نموذج محتوى جديد يستعمله (اختبار يحرس ذلك).
 */
trait FlushesContentCache
{
    public static function bootFlushesContentCache(): void
    {
        static::saved(fn () => ContentCache::flush());
        static::deleted(fn () => ContentCache::flush());
    }
}
