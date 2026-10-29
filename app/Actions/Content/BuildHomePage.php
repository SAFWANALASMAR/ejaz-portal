<?php

namespace App\Actions\Content;

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
use App\Support\PortalStats;
use Illuminate\Database\Eloquent\Collection;
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
        'governance' => ['icon' => 'shield-check', 'route' => 'governance.index'],
        'membership' => ['icon' => 'user-group', 'route' => 'services.assembly-membership'],
        'services' => ['icon' => 'squares-2x2', 'route' => 'programs.index'],
        // أيقونة مستقلة للمسابقات (ملاحظة المدير التنفيذي)
        'competitions' => ['icon' => 'trophy', 'route' => 'competitions.index'],
        'media' => ['icon' => 'megaphone', 'route' => 'media.index'],
        // المتجر المعتمد على سلة (قرار صفوان): /store يحوّل إليه، ويفتح في تبويب جديد
        'store' => ['icon' => 'shopping-bag', 'route' => 'store.index', 'external' => true],
    ];

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
     * الرئيسية بترتيب المدير التنفيذي (ملاحظاته عبر صفوان، 25 أكتوبر 2026): كلمة خادم الحرمين الشريفين، ثم كلمة رئيس مجلس
     * الإدارة، ثم المبررات، ثم القيم الأربع (content — HomeContent)، ثم شريط الأخبار، ثم شريط الإعلانات، ثم قنوات التواصل.
     * والمقاطع السابقة (البطل والإحصائيات والاختصارات والمقطع والمبادرات والمرئيات والشركاء) باقية تُظهر من الإعدادات،
     * ومخفية ما لم تُظهر صراحة.
     *
     * @return array{
     *     content: array<string, mixed>,
     *     hero: array{title: string, text: string, image: string, alt: string, button: array{label: string, url: string}|null}|null,
     *     video: string|null,
     *     video_thumbnail: string|null,
     *     stats: list<array{label: string, value: int, suffix: string, icon: string|null}>,
     *     shortcuts: list<array{key: string, icon: string, url: string, external: bool}>,
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

        return [
            'content' => HomeContent::resolved(),
            'hero' => self::shows($home, 'hero', false) ? self::hero($home) : null,
            // مقطع الرئيسية (من الموقع السابق): معرّف يوتيوب من الإعدادات، يُفحص الرابط عند العرض
            'video' => $video = self::shows($home, 'video', false) ? VideoUrl::youtubeId(is_string($home['video_url'] ?? null) ? $home['video_url'] : null) : null,
            // وصورته التي جلبها الخادم (FetchHomeVideoThumbnail)، وإلا الخلفية الداكنة
            'video_thumbnail' => $video !== null ? FetchHomeVideoThumbnail::url($video) : null,
            'stats' => self::shows($home, 'stats', false) ? self::stats($home) : [],
            'shortcuts' => self::shows($home, 'shortcuts', false) ? self::shortcuts() : [],
            // ثلاث بطاقات كما في الصورة 07، والبقية في صفحة المبادرات
            'initiatives' => self::shows($home, 'initiatives', false) ? Initiative::query()->published()->limit(3)->get() : new Collection,
            // مفتاح «آخر الأخبار»: عناوين أحدث المنشور في الشريط المتحرك
            'news' => self::shows($home, 'latest') ? NewsArticle::query()->published()->newestFirst()->limit(self::TICKER_NEWS)->get() : new Collection,
            'announcements' => self::shows($home, 'announcements') ? Announcement::query()->current()->limit(self::ANNOUNCEMENTS)->get() : new Collection,
            'videos' => self::shows($home, 'videos', false) ? self::videos() : new Collection,
            'accounts' => self::shows($home, 'accounts') ? self::accounts() : [],
            // اثنا عشر شريكاً واثنا عشر راعياً كلٌّ باستعلامه، فلا يحجب أحدهما الآخر، والبقية في صفحة الشركاء
            'partners' => self::shows($home, 'partners', false) ? Partner::query()->published()->where('kind', PartnerKind::Partner)->limit(12)->get() : new Collection,
            'sponsors' => self::shows($home, 'partners', false) ? Partner::query()->published()->where('kind', PartnerKind::Sponsor)->limit(12)->get() : new Collection,
        ];
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
     * @return list<array{key: string, icon: string, url: string, external: bool}>
     */
    private static function shortcuts(): array
    {
        $shortcuts = [];

        foreach (self::SHORTCUTS as $key => $shortcut) {
            if (Route::has($shortcut['route'])) {
                $shortcuts[] = ['key' => $key, 'icon' => $shortcut['icon'], 'url' => route($shortcut['route']), 'external' => $shortcut['external'] ?? false];
            }
        }

        return $shortcuts;
    }
}
