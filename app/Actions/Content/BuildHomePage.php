<?php

namespace App\Actions\Content;

use App\Enums\CompetitionStatus;
use App\Enums\LibrarySection;
use App\Enums\PartnerKind;
use App\Enums\SiteIcon;
use App\Filament\Pages\SiteSettings;
use App\Jobs\FetchHomeVideoThumbnail;
use App\Models\Announcement;
use App\Models\Initiative;
use App\Models\LibraryItem;
use App\Models\NewsArticle;
use App\Models\Partner;
use App\Models\Setting;
use App\Rules\SafeUrl;
use App\Rules\VideoUrl;
use App\Support\HomeContent;
use App\Support\HomePortalStats;
use App\Support\HomeShowcase;
use App\Support\PortalStats;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

/**
 * مقاطع الرئيسية من «إعدادات الموقع» (المفتاح home)، وبدائل موثقة لما لم يُدخل بعد:
 * اسم الجمعية واختصاصها، والرسم المؤقت المحايد. ولا رقم ولا زر افتراضي (التصحيحات
 * في docs/design/ui-reference.md).
 */
class BuildHomePage
{
    /**
     * اختصارات الأقسام (الصورة 07). البطاقة تظهر حين يُبنى قسمها (يوجد مساره)، فلا رابط معطل.
     * أسماء المسارات عقد مع بنودها في docs/roadmap.md، فلا تُغيَّر هناك دون تغييرها هنا.
     */
    public const SHORTCUTS = [
        // بترتيب لوحة التصور النهائي (النسخة 63 — Final-Home)، و«الخدمات الإلكترونية» تضم الدورات والمسابقات (البند R4)، والمبادرات في «عن
        // الجمعية» لا في «خدماتنا» (ملاحظات المدير التنفيذي، 4 نوفمبر 2026)
        'services' => ['icon' => 'squares-2x2', 'route' => 'programs.index'],
        'membership' => ['icon' => 'user-group', 'route' => 'services.assembly-membership'],
        'governance' => ['icon' => 'shield-check', 'route' => 'governance.index'],
        // أيقونة مستقلة للمسابقات (ملاحظة المدير التنفيذي)
        'competitions' => ['icon' => 'trophy', 'route' => 'competitions.index'],
        'library' => ['icon' => 'book-open', 'route' => 'library.index'],
        'media' => ['icon' => 'megaphone', 'route' => 'media.index'],
        // أيقونة للشركاء في الرئيسية (البند H5)
        'partners' => ['icon' => 'hand-raised', 'route' => 'media.partners'],
        // «قياس الرضا» ظاهرة دائماً كما في صفحة الخدمات (إضافة توجيه المدير، 3 نوفمبر 2026، البند ١٧): الخدمات التسع في الموضعين
        'surveys' => ['icon' => 'face-smile', 'route' => 'surveys.index'],
    ];

    /**
     * لون مربع الأيقونة لكل بطاقة في «خدماتنا» (x-icon-tile): من ألوان الهوية بالتناوب.
     */
    public const SHORTCUT_TONES = ['services' => 'pale', 'membership' => 'light', 'governance' => 'medium', 'competitions' => 'dark', 'library' => 'light', 'media' => 'medium', 'partners' => 'dark', 'surveys' => 'pale'];

    /**
     * نماذج الفيديو في الرئيسية: أحدث ثلاثة مختارة منشورة برابط يوتيوب.
     */
    public const VIDEOS = 3;

    /**
     * عناوين الأخبار في الشريط المتحرك.
     */
    public const TICKER_NEWS = 10;

    /**
     * الإعلانات في شريطها، والبقية في صفحة الإعلانات.
     */
    public const ANNOUNCEMENTS = 12;

    /**
     * عناصر كل شريط متحرك في الرئيسية (المرئيات، وأغلفة الكتب) وبطاقة «مبادراتنا».
     */
    public const STRIP = 12;

    /**
     * الرئيسية بترتيب المدير التنفيذي (ملاحظاته عبر صفوان، 25 أكتوبر 2026): كلمة خادم الحرمين الشريفين، ثم كلمة رئيس مجلس
     * الإدارة، ثم المبررات، ثم القيم الأربع (content — HomeContent)، ثم شريط الأخبار، ثم شريط الإعلانات، ثم قنوات التواصل.
     * والمقاطع السابقة (البطل والإحصائيات والاختصارات والمقطع والمبادرات والمرئيات والشركاء) باقية تُظهر من الإعدادات،
     * ومخفية ما لم تُظهر صراحة.
     *
     * @return array{
     *     content: array<string, mixed>,
     *     hero: array{title: string, text: string, image: string, alt: string, button: array{label: string, url: string}|null},
     *     slides: list<array{key: string, src: string, srcset: ?string, alt: string, title: string, subtitle: ?string, text: ?string, buttons: list<array{label: string, url: string, secondary: bool}>, badges: list<string>, missing: bool}>,
     *     identity: list<array{kind: string, title: string, text: string, icon: string}>,
     *     identity_texts: array{goals: string, principles: string},
     *     prize: array{title: string, total: int, url: string, status: CompetitionStatus, text: ?string, date: Carbon, image: ?string}|null,
     *     top_initiatives: Collection<int, Initiative>,
     *     strip_videos: Collection<int, LibraryItem>,
     *     strip_books: Collection<int, LibraryItem>,
     *     x_account: array{url: string, handle: string|null}|null,
     *     video: string|null,
     *     video_thumbnail: string|null,
     *     stats: list<array{label: string, value: int, suffix: string, icon: string|null}>,
     *     portal_chart: array{line: string, area: string, total: int, max: int}|null,
     *     shortcuts: list<array{key: string, icon: string, url: string, external: bool, tone: string, title: string, text: string}>,
     *     initiatives: Collection<int, Initiative>,
     *     news: Collection<int, NewsArticle>,
     *     announcements: Collection<int, Announcement>,
     *     videos: Collection<int, LibraryItem>,
     *     accounts: list<array{network: string, url: string}>,
     *     partners: Collection<int, Partner>,
     *     sponsors: Collection<int, Partner>,
     * }
     */
    public function handle(): array
    {
        $home = Setting::group('home');
        $content = HomeContent::resolved();

        $hero = self::hero($home);
        // المخزّن وحده (ejaz:refresh-stats)، فلا استعلام للأرقام عند بناء الرئيسية
        $portal = HomeContent::showsPortalStats() ? HomePortalStats::cached() : null;

        return [
            'content' => $content,
            'hero' => $hero,
            // شرائح البطل بترتيبها المعتمد (أمر التصور النهائي، النسخة 63)، وشرائح الهوية، والجوائز (HomeShowcase)
            'slides' => HomeShowcase::slides($home, $content, $hero),
            'identity' => HomeShowcase::identity($content),
            // نصا «أهدافنا» و«مبادئنا»، وإلا من مقطعيهما في «عن الجمعية» المنشورة
            'identity_texts' => HomeShowcase::identityTexts($content),
            'prize' => HomeShowcase::prize(),
            // ملاحظات المدير (31 أكتوبر 01:28): «مبادراتنا» بطاقة تحت البطل، وشريطا المرئيات وأغلفة الكتب بدل «المستجدات»
            'top_initiatives' => Initiative::query()->published()->limit(self::STRIP)->get(['id', 'slug', 'name', 'summary']),
            'strip_videos' => self::stripVideos(),
            // بطاقة «تابعنا على X» من حساب إكس في «الحسابات الرسمية» (قرار صفوان، 31 أكتوبر 01:38): بلا سكربت من إكس، وتختفي بلا رابط
            'x_account' => self::xAccount(),
            'strip_books' => self::stripLibrary([LibrarySection::Books]),
            // وشريط «الأبحاث والدراسات» خرج من الرئيسية: شريط واحد للمكتبة (ملاحظات المدير ليلة 5 نوفمبر 2026، البند 3)، وقسمه باقٍ في المكتبة
            // مقطع الرئيسية (من الموقع السابق): معرّف يوتيوب من الإعدادات، يُفحص الرابط عند العرض
            'video' => $video = self::shows($home, 'video', false) ? VideoUrl::youtubeId(is_string($home['video_url'] ?? null) ? $home['video_url'] : null) : null,
            // وصورته التي جلبها الخادم (FetchHomeVideoThumbnail)، وإلا الخلفية الداكنة
            'video_thumbnail' => $video !== null ? FetchHomeVideoThumbnail::url($video) : null,
            // «البوابة بالأرقام» (البند H9): الأرقام الآلية من المخزّن (HomePortalStats) ثم المعتمدة من «إعدادات الموقع»، ورسم آخر 30 يوماً
            'stats' => [...self::portalCards($portal), ...(self::shows($home, 'stats', false) ? self::stats($home) : [])],
            'portal_chart' => $portal !== null ? HomePortalStats::chart($portal['days']) : null,
            'shortcuts' => self::shortcuts(),
            // ثلاث بطاقات كما في الصورة 07، والبقية في صفحة المبادرات
            'initiatives' => self::shows($home, 'initiatives', false) ? Initiative::query()->published()->limit(3)->get() : new Collection,
            // مفتاح «آخر الأخبار»: عناوين أحدث المنشور في الشريط المتحرك
            'news' => self::shows($home, 'latest') ? NewsArticle::query()->published()->newestFirst()->limit(self::TICKER_NEWS)->get() : new Collection,
            'announcements' => self::shows($home, 'announcements') ? Announcement::query()->current()->limit(self::ANNOUNCEMENTS)->get() : new Collection,
            'videos' => self::shows($home, 'videos', false) ? self::videos() : new Collection,
            'accounts' => self::shows($home, 'accounts') ? self::accounts() : [],
            // اثنا عشر شريكاً واثنا عشر راعياً كلٌّ باستعلامه، فلا يحجب أحدهما الآخر، والبقية في صفحة الشركاء
            'partners' => self::shows($home, 'partners') ? Partner::query()->published()->where('kind', PartnerKind::Partner)->limit(12)->get() : new Collection,
            'sponsors' => self::shows($home, 'partners') ? Partner::query()->published()->where('kind', PartnerKind::Sponsor)->limit(12)->get() : new Collection,
        ];
    }

    /**
     * شريط «جديد مكتبة إعجاز وقناتها» في لوحة المستفيد (Final-Dashboard — البند D1): المقاطع والأغلفة نفسها التي في شريطي الرئيسية.
     *
     * @return array{videos: Collection<int, LibraryItem>, books: Collection<int, LibraryItem>}
     */
    public static function libraryStrip(): array
    {
        return ['videos' => self::stripVideos(), 'books' => self::stripLibrary([LibrarySection::Books, LibrarySection::Research])];
    }

    /**
     * شريطا الكتب والأبحاث في الرئيسية منفصلان (إضافة توجيه المدير التنفيذي، 3 نوفمبر 2026، البند ٢٦): «الكتب والمؤلفات» ثم «الأبحاث
     * والدراسات» تحته، وكانا شريطاً واحداً (البند R9). ولوحة المستفيد تبقى بالقسمين معاً (libraryStrip).
     *
     * @param  list<LibrarySection>  $sections
     * @return Collection<int, LibraryItem>
     */
    private static function stripLibrary(array $sections): Collection
    {
        // الكتب والأبحاث (عنوان الشريط «الكتب والمؤلفات والأبحاث»)، والغلاف إن رُفع وإلا مربع ملون بأيقونة كما في التصور: كان الشرط
        // غلافاً مرفوعاً فاختفى الشريط من رابط العرض (ملاحظات المدير التنفيذي، 1 نوفمبر 2026، البند ٢٤)
        return LibraryItem::query()->published()->whereIn('section', $sections)->newestFirst()->limit(self::STRIP)->get();
    }

    /**
     * شريط المرئيات: أحدث مقاطع يوتيوب في المكتبة، بصورها التي جلبها الخادم وإلا مربع ملون بزر التشغيل (البند ٢٤: كانت الصورة
     * شرطاً، فبلا جلبها — YOUTUBE_THUMBNAILS — اختفى الشريط). ولا تشغيل في الرئيسية: الضغط إلى صفحة المقطع.
     *
     * @return Collection<int, LibraryItem>
     */
    private static function stripVideos(): Collection
    {
        return LibraryItem::query()
            ->published()
            ->where('section', LibrarySection::Videos)
            ->whereNotNull('video_url')
            ->newestFirst()
            ->limit(self::STRIP * 2)
            ->get()
            ->filter(fn (LibraryItem $item): bool => $item->youtubeId() !== null)
            ->take(self::STRIP)
            ->values();
    }

    /**
     * يوتيوب وحده (ملاحظة المدير التنفيذي: «ربط الفيديوهات بيوتيوب حتى لا تأخذ سعة كبيرة»).
     *
     * @return Collection<int, LibraryItem>
     */
    private static function videos(): Collection
    {
        return LibraryItem::query()
            ->published()
            ->where('section', LibrarySection::Videos)
            ->where('on_home', true)
            ->whereNotNull('video_url')
            ->newestFirst()
            ->limit(self::VIDEOS * 4)
            ->get()
            ->filter(fn (LibraryItem $item): bool => $item->youtubeId() !== null)
            ->take(self::VIDEOS)
            ->values();
    }

    /**
     * حسابات الجمعية الرسمية من إعدادات الموقع، بترتيب المنصات فيها، والرابط الآمن وحده.
     *
     * @return list<array{network: string, url: string}>
     */
    private static function accounts(): array
    {
        $social = (array) (Setting::group('contact')['social'] ?? []);
        $accounts = [];

        foreach (SiteSettings::SOCIAL as $network) {
            $url = $social[$network] ?? null;

            if (is_string($url) && $url !== '' && SafeUrl::isSafe($url) && str_starts_with($url, 'https://')) {
                $accounts[] = ['network' => $network, 'url' => $url];
            }
        }

        return $accounts;
    }

    /**
     * حساب إكس الرسمي من «إعدادات الموقع» (contact.social.x): رابط https إلى x.com أو twitter.com، واسم الحساب من مساره.
     *
     * @return array{url: string, handle: string|null}|null
     */
    private static function xAccount(): ?array
    {
        $url = ((array) (Setting::group('contact')['social'] ?? []))['x'] ?? null;

        if (! is_string($url) || ! str_starts_with($url, 'https://') || ! SafeUrl::isSafe($url)) {
            return null;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        if (! in_array(preg_replace('/^(www|mobile)\./', '', $host), ['x.com', 'twitter.com'], true)) {
            return null;
        }

        $handle = explode('/', trim((string) parse_url($url, PHP_URL_PATH), '/'))[0];

        return ['url' => $url, 'handle' => preg_match('/^[A-Za-z0-9_]{1,15}$/', $handle) === 1 ? '@'.$handle : null];
    }

    /**
     * المقطع كما ضُبط في الإعدادات، وإلا افتراضه: مقاطع المدير التنفيذي ظاهرة، والسابقة مخفية.
     *
     * @param  array<string, mixed>  $home
     */
    private static function shows(array $home, string $section, bool $default = true): bool
    {
        return (bool) ($home['show_'.$section] ?? $default);
    }

    /**
     * @param  array<string, mixed>  $home
     * @return array{title: string, text: string, image: string, alt: string, button: array{label: string, url: string}|null}
     */
    private static function hero(array $home): array
    {
        $text = fn (string $key): ?string => is_string($home[$key] ?? null) && trim($home[$key]) !== '' ? trim($home[$key]) : null;

        $label = $text('hero_button_label');
        $url = $text('hero_button_url');
        $image = $text('hero_image');

        return [
            'title' => $text('hero_title') ?? __('site.name'),
            'text' => $text('hero_text') ?? __('site.tagline'),
            'image' => $image !== null ? Storage::disk('public')->url($image) : asset('images/placeholders/architecture.svg'),
            // الصورة المؤقتة زخرفية، والمرفوعة نصها البديل من الإعدادات
            'alt' => $image !== null ? (string) $text('hero_image_alt') : '',
            // يُفحص الرابط مرة أخرى عند العرض، فلا يظهر رابط ضار ولو وصل إلى القاعدة
            'button' => $label !== null && $url !== null && SafeUrl::isSafe($url) ? ['label' => $label, 'url' => $url] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $home
     * @return list<array{label: string, value: int, suffix: string, icon: string|null}>
     */
    private static function stats(array $home): array
    {
        $stats = [];

        $live = PortalStats::cached() ?? [];

        foreach ((array) ($home['stats'] ?? []) as $stat) {
            if (! is_array($stat) || ! is_string($stat['label'] ?? null) || trim($stat['label']) === '') {
                continue;
            }

            // الخانة الآلية من بيانات البوابة المحسوبة في الخلفية، فلا تظهر قبل أول حساب
            $source = is_string($stat['source'] ?? null) && in_array($stat['source'], PortalStats::PUBLIC, true) ? $stat['source'] : null;
            $value = $source !== null ? ($live[$source] ?? null) : ($stat['value'] ?? null);

            if (! is_numeric($value)) {
                continue;
            }

            $stats[] = [
                'label' => trim($stat['label']),
                'value' => (int) $value,
                'suffix' => is_string($stat['suffix'] ?? null) ? $stat['suffix'] : '',
                // الأيقونة من القائمة المسموحة فقط، فاسمها يبني مسار ملف
                'icon' => is_string($stat['icon'] ?? null) ? SiteIcon::tryFrom($stat['icon'])?->value : null,
            ];
        }

        return $stats;
    }

    /**
     * بطاقات الأرقام الآلية الأربع معاً (2×2): الزيارات، والمسجلون في المسابقات، ثم قراءات الكتب ومشاهدات المقاطع بعدد المرات.
     * تظهر الأربع دائماً متى حُسبت الأرقام والقسم ظاهر، والصفر رقمه الحقيقي «0» (توجيه المدير التنفيذي وإضافته، 3 نوفمبر 2026 —
     * docs/decisions/2026-11-03-director-home.md): كانت كل بطاقة صفرية تُخفى، فلم يبق في العرض بلا قراءة في «قراءاتي» إلا
     * بطاقتان. وقبل أول حساب في الخلفية لا بطاقة.
     *
     * @param  array{visits: int, registrants: int, book_reads: int, media_views: int, days: list<int|null>}|null  $portal
     * @return list<array{label: string, value: int, suffix: string, icon: string|null}>
     */
    private static function portalCards(?array $portal): array
    {
        if ($portal === null) {
            return [];
        }

        return [
            ['label' => __('home.portal_stats.visits'), 'value' => $portal['visits'], 'suffix' => '', 'icon' => SiteIcon::Eye->value],
            ['label' => __('home.portal_stats.registrants'), 'value' => $portal['registrants'], 'suffix' => '', 'icon' => SiteIcon::Trophy->value],
            ['label' => __('home.portal_stats.book_reads'), 'value' => $portal['book_reads'], 'suffix' => '', 'icon' => SiteIcon::BookOpen->value],
            ['label' => __('home.portal_stats.media_views'), 'value' => $portal['media_views'], 'suffix' => '', 'icon' => SiteIcon::VideoCamera->value],
        ];
    }

    /**
     * @return list<array{key: string, icon: string, url: string, external: bool, tone: string, title: string, text: string}>
     */
    private static function shortcuts(): array
    {
        $shortcuts = [];

        foreach (self::SHORTCUTS as $key => $shortcut) {
            if (Route::has($shortcut['route'])) {
                // البطاقتان المضافتان في النسخة 63 نصاهما في home.shortcuts_final
                $text = Lang::has('home.shortcuts.'.$key.'.title') ? 'home.shortcuts.'.$key : 'home.shortcuts_final.'.$key;
                $shortcuts[] = [
                    'key' => $key, 'icon' => $shortcut['icon'], 'url' => route($shortcut['route']), 'external' => false, 'tone' => self::SHORTCUT_TONES[$key],
                    'title' => (string) __($text.'.title'), 'text' => (string) __($text.'.text'),
                ];
            }
        }

        return $shortcuts;
    }
}
