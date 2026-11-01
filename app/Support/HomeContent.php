<?php

namespace App\Support;

use App\Enums\SiteIcon;
use App\Models\Setting;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Storage;

/**
 * مقاطع الرئيسية من الخطة الاستراتيجية (ملاحظات المدير التنفيذي عبر صفوان، 25 أكتوبر 2026): كلمة خادم الحرمين الشريفين،
 * وكلمة رئيس مجلس الإدارة، والمبررات، والقيم الأربع. نصوصها الأصلية منقولة حرفياً في lang/ar/home.php (sections)،
 * وصورها في public/images/home، وكل نص وصورة يُستبدل من اللوحة («محتوى الرئيسية» — UpdateHomeContent) ويُحفظ في
 * الإعدادات (المفتاح home_sections). فما لم يُعدَّل يبقى على الأصل.
 *
 * ومعها النصوص الثابتة في الواجهة (مراجعة المشرف، 26 أكتوبر 2026): عنوانا شريطي الأخبار والإعلانات، وعنوان «قنوات التواصل»
 * ومقدمتها، وعنوان صفحة الإعلانات ومقدمتها، ونصوص التذييل. وملفات الشعار والمخطوطة في الرأس والتذييل والمبررات.
 */
final class HomeContent
{
    public const KEY = 'home_sections';

    /**
     * عدد القيم في الخطة الاستراتيجية (الصفحتان 9 و10).
     */
    public const VALUES = 4;

    /**
     * النصوص لكل مقطع.
     *
     * @var array<string, list<string>>
     */
    public const TEXTS = [
        'king' => ['heading', 'text', 'signature_title', 'signature_name'],
        'chairman' => ['heading', 'excerpt', 'full', 'name', 'title'],
        'rationale' => ['heading', 'text', 'lead', 'outcome'],
        'values' => ['heading'],
        'tickers' => ['news', 'announcements'],
        'channels' => ['title', 'intro'],
        'announcements' => ['title', 'intro'],
        // supervision باقٍ مفتاحاً ولا يُعرض في الرأس ولا التذييل منذ النسخة 63 من التصور (البند R2)
        'footer' => ['about', 'supervision', 'license', 'quick_links', 'contact', 'channels', 'rights', 'hours', 'accounts'],
        // مربع الترخيص في الرأس (البند R2)
        'header' => ['licensed_by', 'license_number'],
        // شريحة الهوية تحت البطل وبطاقة «خدماتنا» الجانبية (مرجع المدير، 30 أكتوبر — plans/2026-10-30-director-home-reference.md).
        // أصلها فارغ، فالرؤية والرسالة من صفحة «عن الجمعية» المنشورة ما لم تُدخل هنا (HomeIdentity)
        'identity' => ['vision', 'mission', 'side_title', 'side_text', 'goals', 'principles'],
        // عناوين أقسام الرئيسية وبطاقاتها الظاهرة للزائر (أمر التصور النهائي، النسخة 63 — المرحلة ب)
        'headings' => ['vision', 'mission', 'goals', 'values', 'principles', 'initiatives', 'all_initiatives', 'services', 'services_intro', 'videos', 'books', 'x', 'x_follow', 'partners', 'stats'],
    ];

    /**
     * أيقونة كل قيمة ما لم تُختر من اللوحة (شرائح الهوية في الرئيسية)، من القائمة المسموحة (SiteIcon).
     *
     * @var list<string>
     */
    public const VALUE_ICONS = ['check-badge', 'light-bulb', 'hand-raised', 'puzzle-piece'];

    /**
     * النصوص الطويلة (حد 6000 حرف، وغيرها 300).
     */
    public const LONG = ['full', 'text', 'excerpt', 'vision', 'mission', 'goals', 'principles'];

    /**
     * نصوص كتبناها مكان محتوى ناقص من الجمعية، فتُوسم «مثال تجريبي» ما لم تُستبدل من اللوحة (قرار صفوان، 26 أكتوبر 2026).
     */
    public const SAMPLE = ['channels.intro', 'announcements.intro'];

    /**
     * صور المقاطع (المسار على القرص العام) بأصلها في public/images/home.
     *
     * @var array<string, string>
     */
    public const IMAGES = [
        'king' => 'images/home/king.jpg',
        'chairman' => 'images/home/chairman.jpg',
        'rationale' => 'images/home/rationale.jpg',
    ];

    /**
     * ملفات الشعار (الأعمدة) والمخطوطة بلونيها، بأصلها في public/images/brand، وتُستبدل من اللوحة متى وصلت ملفات أفضل.
     *
     * @var array<string, string>
     */
    public const BRAND = [
        'mark' => 'images/brand/logo-mark.png',
        'mark_white' => 'images/brand/logo-mark-white.png',
        'calligraphy' => 'images/brand/logo-calligraphy.png',
        'calligraphy_white' => 'images/brand/logo-calligraphy-white.png',
    ];

    /**
     * شرائح بطل الرئيسية بترتيبها (أمر التصور النهائي، النسخة 63 — البند H1). صورة كل شريحة تُرفع من «محتوى الرئيسية» وتُحفظ في
     * hero_slides.<الشريحة>، وبلا صورة بديلها في HomeShowcase::slides.
     *
     * @var list<string>
     */
    public const HERO_SLIDES = ['about', 'king', 'chairman', 'brahin', 'library'];

    /**
     * المحتوى للعرض: المحفوظ من اللوحة، وإلا الأصل.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function resolved(): array
    {
        $stored = Setting::group(self::KEY);
        $content = [];

        foreach (self::TEXTS as $section => $keys) {
            foreach ($keys as $key) {
                $content[$section][$key] = self::clean($stored[$section][$key] ?? null) ?? self::default($section, $key);
            }
        }

        foreach (self::IMAGES as $section => $default) {
            $path = self::clean($stored[$section]['image'] ?? null);
            $content[$section]['image'] = $path !== null ? Storage::disk('public')->url($path) : asset($default);
            $content[$section]['image_alt'] = self::clean($stored[$section]['image_alt'] ?? null) ?? (string) __('home.sections.'.$section.'.image_alt');
        }

        $content['values']['items'] = [];

        foreach (range(0, self::VALUES - 1) as $index) {
            $item = (array) ($stored['values']['items'][$index] ?? []);
            $path = self::clean($item['image'] ?? null);

            $content['values']['items'][] = [
                'title' => self::clean($item['title'] ?? null) ?? (string) __('home.sections.values.items.'.$index.'.title'),
                'text' => self::clean($item['text'] ?? null) ?? (string) __('home.sections.values.items.'.$index.'.text'),
                'image' => $path !== null ? Storage::disk('public')->url($path) : asset('images/home/value-'.($index + 1).'.jpg'),
                'icon' => SiteIcon::tryFrom(is_string($item['icon'] ?? null) ? $item['icon'] : '')->value ?? self::VALUE_ICONS[$index],
            ];
        }

        return $content;
    }

    /**
     * نص واحد للعرض خارج الرئيسية (التذييل، وصفحة الإعلانات): المحفوظ، وإلا الأصل.
     */
    public static function text(string $section, string $key): string
    {
        return self::clean(Setting::group(self::KEY)[$section][$key] ?? null) ?? self::default($section, $key);
    }

    /**
     * النص «مثال تجريبي» ما دام الأصل الذي كتبناه لم يُستبدل من اللوحة.
     */
    public static function isSample(string $section, string $key): bool
    {
        return in_array($section.'.'.$key, self::SAMPLE, true) && self::clean(Setting::group(self::KEY)[$section][$key] ?? null) === null;
    }

    /**
     * مسار صورة شريحة البطل المرفوعة من اللوحة على القرص العام، أو null.
     */
    public static function heroSlideImage(string $key): ?string
    {
        return self::clean(Setting::group(self::KEY)['hero_slides'][$key] ?? null);
    }

    /**
     * صورة المقطع (خادم الحرمين الشريفين، ورئيس المجلس، والمبررات) مرفوعة من اللوحة لا الأصل المؤقت. وبدونها يُوسم إطارها
     * «صورة معتمدة من الجمعية» في التطوير (x-missing).
     */
    public static function hasStoredImage(string $section): bool
    {
        return self::clean(Setting::group(self::KEY)[$section]['image'] ?? null) !== null;
    }

    /**
     * رابط صورة شريحة البطل المرفوعة من اللوحة، أو null. وتستعملها صفحتها الداخلية صورةً لبطلها (المرحلة ج من التصور النهائي):
     * مكتبة إعجاز بصورة شريحتها، ومسابقة البراهين بصورة شريحتها، فلا يُضاف موضع صورة جديد.
     */
    public static function heroSlideUrl(string $key): ?string
    {
        $path = self::heroSlideImage($key);

        return $path !== null ? Storage::disk('public')->url($path) : null;
    }

    /**
     * رابط ملف الشعار أو المخطوطة: المرفوع من اللوحة، وإلا الأصل.
     */
    public static function brand(string $key): string
    {
        $path = self::clean(Setting::group(self::KEY)['brand'][$key] ?? null);

        return $path !== null ? Storage::disk('public')->url($path) : asset(self::BRAND[$key]);
    }

    /**
     * الصور المرفوعة من اللوحة في المحفوظ (للتنظيف بعد الاستبدال، ولأمر ejaz:prune-public-files).
     *
     * @param  array<string, mixed>  $stored
     * @return list<string>
     */
    public static function imagePaths(array $stored): array
    {
        $paths = [];

        foreach (array_keys(self::IMAGES) as $section) {
            $paths[] = $stored[$section]['image'] ?? null;
        }

        foreach ((array) ($stored['values']['items'] ?? []) as $item) {
            $paths[] = is_array($item) ? ($item['image'] ?? null) : null;
        }

        foreach (array_keys(self::BRAND) as $key) {
            $paths[] = $stored['brand'][$key] ?? null;
        }

        foreach (self::HERO_SLIDES as $key) {
            $paths[] = $stored['hero_slides'][$key] ?? null;
        }

        return array_values(array_filter($paths, fn (mixed $path): bool => is_string($path) && $path !== ''));
    }

    /**
     * فقرات النص الطويل (كلمة رئيس المجلس): سطر لكل فقرة.
     *
     * @return list<string>
     */
    public static function paragraphs(string $text): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/u', $text) ?: []), fn (string $line): bool => $line !== ''));
    }

    /**
     * النص هو الأصل نفسه (بلا فرق في المسافات حوله ولا في نوع فواصل الأسطر)، فلا يُخزَّن (UpdateHomeContent).
     */
    public static function isDefault(string $section, string $key, mixed $value): bool
    {
        $normalize = fn (string $text): string => trim(str_replace(["\r\n", "\r"], "\n", $text));

        return is_string($value) && $normalize($value) === $normalize(self::default($section, $key));
    }

    /**
     * الأصل: ما في home.sections_final (أصول النسخة 63 من التصور، مضافة لا معدِّلة لما قبلها)، وإلا home.sections.
     */
    private static function default(string $section, string $key): string
    {
        $final = 'home.sections_final.'.$section.'.'.$key;

        return (string) __(Lang::has($final) ? $final : 'home.sections.'.$section.'.'.$key);
    }

    private static function clean(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
