<?php

namespace App\Support;

use App\Enums\RoleName;
use App\Models\Setting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role;

/**
 * ما يشترك فيه حساب حالة المستندات وتنبيهها: تاريخ اليوم بتوقيت الرياض (فالمستند ينتهي بنهاية يومه
 * المحلي)، ومدة التنبيه الافتراضية من الإعداد registers، وإلا من config/registers.php.
 */
final class Registers
{
    public const SETTINGS_KEY = 'registers';

    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::now(config('app.display_timezone'))->startOfDay();
    }

    public static function defaultAlertDays(): int
    {
        $stored = Setting::group(self::SETTINGS_KEY)['default_alert_days'] ?? null;

        return is_numeric($stored) ? (int) $stored : (int) config('registers.default_alert_days');
    }

    /**
     * الحسابات الفعّالة التي تملك الصلاحية بأدوارها، والمشرف العام (يملك الكل دون ربط). لقوائم اختيار
     * المسؤول والمكلَّف في اللوحة، والإجراء يفحص الحساب المختار بـ can() نفسها.
     *
     * @return Builder<User>
     */
    public static function usersWhoCan(string $permission): Builder
    {
        $roles = Role::query()->whereHas('permissions', fn (Builder $query) => $query->where('name', $permission))->pluck('name')->push(RoleName::SuperAdmin->value);

        return User::query()->where('is_active', true)->whereNull('anonymized_at')->whereHas('roles', fn (Builder $query) => $query->whereIn('name', $roles->all()));
    }
}
