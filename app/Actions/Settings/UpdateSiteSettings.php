<?php

namespace App\Actions\Settings;

use App\Models\Setting;
use App\Models\User;
use App\Support\ImageSanitizer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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
        if (! $actor->can('update', Setting::class)) {
            throw new AuthorizationException;
        }

        $previousImage = Setting::group('home')['hero_image'] ?? null;

        DB::transaction(function () use ($groups): void {
            foreach (self::GROUPS as $key) {
                if (array_key_exists($key, $groups)) {
                    Setting::put($key, $groups[$key]);
                }
            }
        });

        $image = $groups['home']['hero_image'] ?? null;

        if (array_key_exists('home', $groups) && $image !== $previousImage) {
            // الصورة الجديدة بلا بيانات وصفية (EXIF)، والقديمة لا تبقى منشورة على رابطها
            if (is_string($image) && $image !== '') {
                ImageSanitizer::strip('public', $image);
            }

            if (is_string($previousImage) && $previousImage !== '') {
                Storage::disk('public')->delete($previousImage);
            }
        }
    }
}
