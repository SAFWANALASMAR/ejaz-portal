<?php

namespace App\Actions\Settings;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * حفظ إعدادات الموقع (التواصل، والتواصل الاجتماعي، والرئيسية).
 * كل مجموعة سجل في جدول settings، والتعديل يُسجَّل ويمسح الذاكرة المؤقتة (نموذج Setting).
 */
class UpdateSiteSettings
{
    public const GROUPS = ['contact', 'home'];

    /**
     * @param  array<string, array<string, mixed>>  $groups
     *
     * @throws AuthorizationException
     */
    public function handle(User $actor, array $groups): void
    {
        if (! $actor->can('settings.update')) {
            throw new AuthorizationException;
        }

        DB::transaction(function () use ($groups): void {
            foreach (self::GROUPS as $key) {
                if (array_key_exists($key, $groups)) {
                    Setting::put($key, $groups[$key]);
                }
            }
        });
    }
}
