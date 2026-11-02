<?php

namespace App\Actions\Settings;

use App\Enums\SiteIcon;
use App\Models\Setting;
use App\Models\User;
use App\Rules\PlainText;
use App\Support\EditablePages;
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

        // صور رؤوس صفحات «محرر الصفحات» تُنشر منه وحده (PublishPageDraft)، فتبقى هنا كما هي في المحفوظ
        foreach (array_keys(array_diff_key(HomeContent::PAGE_HEROES, EditablePages::PAGES)) as $key) {
            $rules['page_heroes.'.$key] = ['nullable', 'string', 'max:255'];
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
        // مفتاح «البوابة بالأرقام» (HomeContent::showsPortalStats)
        $rules['portal_stats.enabled'] = ['sometimes', 'boolean'];

        $validated = Validator::make($content, $rules)->validate();

        // الحفظ يستبدل المجموعة كلها، فحفظ بلا المفتاح يُبقي حالته لا يعيد قسماً أطفأه المحرر ظاهراً
        $validated['portal_stats']['enabled'] = (bool) ($validated['portal_stats']['enabled'] ?? HomeContent::showsPortalStats());

        // النص المطابق لأصله لا يُخزَّن: الصفحة تعرض الأصل في حقوله، فحفظها لسبب آخر لا يجعله نصاً معتمداً، فيبقى وسم
        // «مثال تجريبي» على ما كتبه فريق البوابة، ويتبع العرض أي تحديث لأصله (مراجعة المشرف الأخيرة على #66)
        foreach (HomeContent::TEXTS as $section => $keys) {
            foreach ($keys as $key) {
                if (HomeContent::isDefault($section, $key, $validated[$section][$key] ?? null)) {
                    $validated[$section][$key] = null;
                }
            }
        }

        // صور رؤوس صفحات «محرر الصفحات» خارج التنظيف والحذف هنا: يملكها المحرر ونسخه (ejaz:prune-public-files)
        $before = HomeContent::imagePaths(self::withEditorHeroes(Setting::group(HomeContent::KEY), []));
        $after = HomeContent::imagePaths(self::withEditorHeroes($validated, []));

        PublicImages::prepare($before, $after, UpdateSiteSettings::IMAGE_DIRECTORY, 'images');

        // بقفل صف الإعداد، وصور «محرر الصفحات» من المحفوظ تحت القفل، فلا يمحو الحفظ نشراً متزامناً
        DB::transaction(function () use (&$validated): void {
            $stored = Setting::query()->where('key', HomeContent::KEY)->lockForUpdate()->value('value');
            $validated = self::withEditorHeroes($validated, is_array($stored) ? $stored : []);

            Setting::put(HomeContent::KEY, $validated);
        });

        PublicImages::discard($before, $after, UpdateSiteSettings::IMAGE_DIRECTORY);
    }

    /**
     * صور رؤوس صفحات «محرر الصفحات» من المحفوظ، لا من النموذج (وبمحفوظ فارغ: بلاها).
     *
     * @param  array<string, mixed>  $validated
     * @param  array<string, mixed>  $stored
     * @return array<string, mixed>
     */
    private static function withEditorHeroes(array $validated, array $stored): array
    {
        foreach (array_keys(EditablePages::PAGES) as $key) {
            $path = $stored['page_heroes'][$key] ?? null;
            unset($validated['page_heroes'][$key]);

            if (is_string($path) && $path !== '') {
                $validated['page_heroes'][$key] = $path;
            }
        }

        return $validated;
    }
}
