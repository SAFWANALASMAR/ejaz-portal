<?php

namespace App\Actions\Settings;

use App\Models\Setting;
use App\Models\User;
use App\Support\ImageSanitizer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

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
     * @throws ValidationException صورة لا تُقرأ (المفتاح home.hero_image)
     */
    public function handle(User $actor, array $groups): void
    {
        if (! $actor->can('update', Setting::class)) {
            throw new AuthorizationException;
        }

        $previousImage = Setting::group('home')['hero_image'] ?? null;
        $image = $groups['home']['hero_image'] ?? null;
        $imageChanged = array_key_exists('home', $groups) && $image !== $previousImage;

        // الصورة الجديدة تُنظَّف قبل نشرها: بلا بيانات وصفية (EXIF)، وما لا يُقرأ صورةً
        // يُحذف ويُرفض، ولو اجتاز فحص النوع والأبعاد
        if ($imageChanged && is_string($image) && $image !== '') {
            try {
                ImageSanitizer::strip('public', $image);
            } catch (RuntimeException) {
                Storage::disk('public')->delete($image);

                throw ValidationException::withMessages(['home.hero_image' => __('settings.home.hero_image_unreadable')]);
            }
        }

        DB::transaction(function () use ($groups): void {
            foreach (self::GROUPS as $key) {
                if (array_key_exists($key, $groups)) {
                    Setting::put($key, $groups[$key]);
                }
            }
        });

        // القديمة لا تبقى منشورة على رابطها
        if ($imageChanged && is_string($previousImage) && $previousImage !== '') {
            Storage::disk('public')->delete($previousImage);
        }
    }
}
