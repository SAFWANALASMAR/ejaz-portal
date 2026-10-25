<?php

namespace App\Filament\Concerns;

use App\Enums\RoleName;
use App\Models\User;

/**
 * صفحة إعدادات يطّلع عليها المشرف العام ولا يعدّلها (اطلاع فقط — المدير التنفيذي عبر صفوان، 25 أكتوبر 2026): تُفتح
 * له، ونموذجها معطّل وأزرارها مخفية، والحفظ يرفضه إجراؤه بصلاحيته.
 */
trait ReadableByOverseers
{
    /**
     * من يحفظ في هذه الصفحة.
     */
    abstract protected static function canWrite(): bool;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return static::canWrite() || ($user instanceof User && RoleName::isOverseer($user));
    }

    /**
     * أول كل دالة عامة تحفظ أو تتصل بمزود: إخفاء الزر لا يمنع استدعاءها من المتصفح ($wire.save())، فتُرفض قبل
     * قراءة النموذج، ويبقى فحص الإجراء نفسه بعدها (مراجعة المشرف، 25 أكتوبر 2026).
     */
    protected function authorizeWrite(): void
    {
        abort_unless(static::canWrite(), 403);
    }
}
