<?php

namespace App\Support;

use App\Models\AssemblyMembership;

/**
 * عدد أعضاء الجمعية العمومية الفعّالين للعرض العام في صفحة الحوكمة (المدير عبر صفوان، 1 نوفمبر 2026): رقم محسوب من البيانات
 * بلا أسماء ولا بيانات شخصية، مخزّن في ذاكرة المحتوى، ويُمسح مع الصفحة الأولى للحوكمة عند تغيّر عضوية أو حساب (النموذجان).
 */
final class AssemblyMembersCount
{
    private const KEY = 'assembly.active_members';

    public static function get(): int
    {
        return (int) ContentCache::remember(self::KEY, fn (): int => AssemblyMembership::activeUsers()->count());
    }

    public static function forget(): void
    {
        ContentCache::forget(self::KEY);
        ContentCache::forget('governance.index');
    }
}
