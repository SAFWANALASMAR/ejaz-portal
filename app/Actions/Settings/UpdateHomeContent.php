<?php

namespace App\Actions\Settings;

use App\Enums\SiteIcon;
use App\Models\Setting;
use App\Models\User;
use App\Rules\PlainText;
use App\Support\HomeContent;
use App\Support\PublicImages;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * حفظ نصوص مقاطع الرئيسية وصورها («محتوى الرئيسية» — HomeContent). بصلاحية إعدادات الموقع. والنص الفارغ يعيد المقطع إلى
 * أصله من الخطة الاستراتيجية، والصورة المحذوفة كذلك. والصور الجديدة تُنظَّف قبل نشرها، والمستبدلة تُحذف بعد الحفظ.
 */
class UpdateHomeContent
{
    /**
     * @param  array<string, mixed>  $content
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, array $content): void
    {
        if (! $actor->can('update', Setting::class)) {
            throw new AuthorizationException;
        }

        $rules = [];

        foreach (HomeContent::TEXTS as $section => $keys) {
            foreach ($keys as $key) {
                $long = in_array($key, HomeContent::LONG, true);
                $rules[$section.'.'.$key] = ['nullable', 'string', 'max:'.($long ? 6000 : 300), new PlainText(multiline: $long)];
            }
        }

        foreach (array_keys(HomeContent::BRAND) as $key) {
            $rules['brand.'.$key] = ['nullable', 'string', 'max:255'];
        }

        foreach (HomeContent::HERO_SLIDES as $key) {
            $rules['hero_slides.'.$key] = ['nullable', 'string', 'max:255'];
        }

        foreach (array_keys(HomeContent::IMAGES) as $section) {
            $rules[$section.'.image'] = ['nullable', 'string', 'max:255'];
            $rules[$section.'.image_alt'] = ['nullable', 'string', 'max:300', new PlainText];
        }

        $rules['values.items'] = ['array', 'size:'.HomeContent::VALUES];
        $rules['values.items.*.title'] = ['nullable', 'string', 'max:80', new PlainText];
        $rules['values.items.*.text'] = ['nullable', 'string', 'max:300', new PlainText];
        $rules['values.items.*.image'] = ['nullable', 'string', 'max:255'];
        // أيقونة القيمة في شرائح الهوية: من القائمة المسموحة وحدها، فاسمها يبني مسار ملف
        $rules['values.items.*.icon'] = ['nullable', Rule::enum(SiteIcon::class)];

        $validated = Validator::make($content, $rules)->validate();

        // النص المطابق لأصله لا يُخزَّن: الصفحة تعرض الأصل في حقوله، فحفظها لسبب آخر لا يجعله نصاً معتمداً، فيبقى وسم
        // «مثال تجريبي» على ما كتبه فريق البوابة، ويتبع العرض أي تحديث لأصله (مراجعة المشرف الأخيرة على #66)
        foreach (HomeContent::TEXTS as $section => $keys) {
            foreach ($keys as $key) {
                if (HomeContent::isDefault($section, $key, $validated[$section][$key] ?? null)) {
                    $validated[$section][$key] = null;
                }
            }
        }

        $before = HomeContent::imagePaths(Setting::group(HomeContent::KEY));
        $after = HomeContent::imagePaths($validated);

        PublicImages::prepare($before, $after, UpdateSiteSettings::IMAGE_DIRECTORY, 'images');

        DB::transaction(fn () => Setting::put(HomeContent::KEY, $validated));

        PublicImages::discard($before, $after, UpdateSiteSettings::IMAGE_DIRECTORY);
    }
}
