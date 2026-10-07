<?php

namespace App\Actions\Settings;

use App\Models\Setting;
use App\Models\User;
use App\Support\ImageSanitizer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * حفظ إعدادات الموقع (التواصل، والتواصل الاجتماعي، والرئيسية).
 * كل مجموعة سجل في جدول settings، والتعديل يُسجَّل ويمسح الذاكرة المؤقتة (نموذج Setting).
 */
class UpdateSiteSettings
{
    public const GROUPS = ['contact', 'home'];

    /**
     * مجلد صور الإعدادات على القرص العام.
     */
    public const IMAGE_DIRECTORY = 'settings';

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

        if ($imageChanged && is_string($image) && $image !== '') {
            self::sanitizeImage($image);
        }

        DB::transaction(function () use ($groups): void {
            foreach (self::GROUPS as $key) {
                if (array_key_exists($key, $groups)) {
                    Setting::put($key, $groups[$key]);
                }
            }
        });

        // القديمة لا تبقى منشورة على رابطها
        if ($imageChanged && is_string($previousImage) && self::inImageDirectory($previousImage)) {
            Storage::disk('public')->delete($previousImage);
        }
    }

    private static function inImageDirectory(string $path): bool
    {
        return str_starts_with($path, self::IMAGE_DIRECTORY.'/') && ! str_contains($path, '..');
    }

    /**
     * الصورة الجديدة تُنظَّف قبل نشرها: بلا بيانات وصفية (EXIF). وما لا يُقرأ صورةً يُحذف
     * ويُرفض ولو اجتاز فحص النوع والأبعاد. والمسار من مجلد الإعدادات وحده، فلا يمس الإجراء
     * ملفاً غيره ولو استُدعي من غير صفحة الإعدادات.
     *
     * @throws ValidationException
     */
    private static function sanitizeImage(string $path): void
    {
        $reject = fn (): ValidationException => ValidationException::withMessages([
            'home.hero_image' => __('settings.home.hero_image_unreadable'),
        ]);

        if (! self::inImageDirectory($path)) {
            throw $reject();
        }

        try {
            ImageSanitizer::strip('public', $path);
        } catch (Throwable) {
            Storage::disk('public')->delete($path);

            throw $reject();
        }
    }
}
