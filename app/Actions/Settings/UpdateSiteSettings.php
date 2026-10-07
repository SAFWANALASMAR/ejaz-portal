<?php

namespace App\Actions\Settings;

use App\Models\Setting;
use App\Models\User;
use App\Support\PublicImages;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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

        $before = self::imagePaths(Setting::group('home')['hero_image'] ?? null);
        $after = array_key_exists('home', $groups) ? self::imagePaths($groups['home']['hero_image'] ?? null) : $before;

        PublicImages::prepare($before, $after, self::IMAGE_DIRECTORY, 'home.hero_image');

        DB::transaction(function () use ($groups): void {
            foreach (self::GROUPS as $key) {
                if (array_key_exists($key, $groups)) {
                    Setting::put($key, $groups[$key]);
                }
            }
        });

        PublicImages::discard($before, $after, self::IMAGE_DIRECTORY);
    }

    /**
     * @return list<string>
     */
    private static function imagePaths(mixed $image): array
    {
        return is_string($image) && $image !== '' ? [$image] : [];
    }
}
