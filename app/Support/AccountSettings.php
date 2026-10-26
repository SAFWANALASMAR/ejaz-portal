<?php

namespace App\Support;

use App\Models\Setting;
use Carbon\CarbonImmutable;

/**
 * إعدادات الحسابات من اللوحة (المفتاح accounts، صلاحية accounts.update — ملاحظات المدير التنفيذي عبر صفوان، 25 أكتوبر
 * 2026): «ما ودنا نربطها برقم الجوال يصير يجيه رابط للتوثيق، خليها فيما بعد».
 * - verification: توثيق البريد والجوال شرطاً للتسجيل في البرامج وتقديم الطلبات، ورسالة التوثيق الآلية بعد التسجيل،
 *   وتنبيهات التوثيق في لوحة المستخدم، وحذف الحسابات المتروكة غير الموثقة. مطفأ افتراضياً، ويُفعَّل لاحقاً.
 * - verification_enabled_at: لحظة تفعيله (UpdateSiteSettings)، فلا يُحذف حساب سُجّل والتوثيق مطفأ (PruneUnverifiedAccounts).
 * - show_deferred_portals: بوابات «مجلس الإدارة» و«الموظفون» في اللوحة و«الجمعية العمومية» في حساب المستخدم.
 *   مخفية افتراضياً حتى يقرر المدير (تؤجل)، والصلاحيات كما هي.
 */
final class AccountSettings
{
    public const KEY = 'accounts';

    public static function verificationEnabled(): bool
    {
        return (bool) (Setting::group(self::KEY)['verification'] ?? false);
    }

    public static function verificationEnabledAt(): ?CarbonImmutable
    {
        $at = Setting::group(self::KEY)['verification_enabled_at'] ?? null;

        return is_string($at) && $at !== '' ? CarbonImmutable::parse($at) : null;
    }

    public static function showsDeferredPortals(): bool
    {
        return (bool) (Setting::group(self::KEY)['show_deferred_portals'] ?? false);
    }
}
