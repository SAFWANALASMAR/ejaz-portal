<?php

namespace App\Actions\Settings;

use App\Jobs\FetchHomeVideoThumbnail;
use App\Models\Setting;
use App\Models\User;
use App\Support\AccountSettings;
use App\Support\PublicImages;
use App\Support\Retention;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * حفظ إعدادات الموقع (التواصل، والتواصل الاجتماعي، والرئيسية، ومدة الاحتفاظ).
 * كل مجموعة سجل في جدول settings، والتعديل يُسجَّل ويمسح الذاكرة المؤقتة (نموذج Setting).
 * ومدة الاحتفاظ تحذف بيانات شخصية آلياً، فلها صلاحيتها (retention.update، للمسؤول التقني وحده). وإعدادات الحسابات
 * (التوثيق والبوابات المؤجلة) بصلاحية accounts.update (AccountSettings).
 */
class UpdateSiteSettings
{
    public const GROUPS = ['contact', 'home', 'retention', 'store', 'donations', AccountSettings::KEY];

    /**
     * مجلد صور الإعدادات على القرص العام.
     */
    // صورة جديدة في الإعدادات تُضاف إلى المرتبط في ejaz:prune-public-files، وإلا حذفها بعد مدة
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

        if (array_key_exists('retention', $groups)) {
            if (! $actor->can('retention.update')) {
                throw new AuthorizationException;
            }

            $groups['retention'] = Validator::make($groups['retention'], [
                'days' => ['nullable', 'integer', 'min:1', 'max:'.Retention::MAX_DAYS],
            ], attributes: ['days' => __('settings.retention.days')])->validate();
            $groups['retention']['days'] = isset($groups['retention']['days']) ? (int) $groups['retention']['days'] : null;
        }

        if (array_key_exists(AccountSettings::KEY, $groups)) {
            if (! $actor->can('accounts.update')) {
                throw new AuthorizationException;
            }

            $accounts = array_map(fn (mixed $value): bool => (bool) $value, Validator::make($groups[AccountSettings::KEY], [
                'verification' => ['required', 'boolean'],
                'show_deferred_portals' => ['required', 'boolean'],
            ])->validate());

            // لحظة التفعيل تبقى ما بقي مفعّلاً، وتُضبط عند تفعيله من جديد، فلا تُحذف حسابات فترة الإطفاء
            // (PruneUnverifiedAccounts — مراجعة المشرف المبكرة، ٥)
            $enabledAt = AccountSettings::verificationEnabled() ? AccountSettings::verificationEnabledAt() : null;
            $accounts['verification_enabled_at'] = $accounts['verification'] ? ($enabledAt ?? now())->toIso8601String() : null;

            $groups[AccountSettings::KEY] = $accounts;
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

        // صورة مقطع الرئيسية من الخادم، فلا يتصل متصفح الزائر بجوجل قبل ضغطه
        FetchHomeVideoThumbnail::queueForCurrent();
    }

    /**
     * صور «إعدادات الموقع» المحفوظة في مجلدها (صورة الرئيسية)، فلا يربطها محرر الصفحات بصفحة فيعيد ترميزها أو يحذفها (EditablePages).
     *
     * @return list<string>
     */
    public static function storedImages(): array
    {
        return self::imagePaths(Setting::group('home')['hero_image'] ?? null);
    }

    /**
     * @return list<string>
     */
    private static function imagePaths(mixed $image): array
    {
        return is_string($image) && $image !== '' ? [$image] : [];
    }
}
