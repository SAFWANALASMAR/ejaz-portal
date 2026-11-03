<?php

namespace App\Support;

use App\Enums\CompetitionStatus;
use App\Models\Album;
use App\Models\Announcement;
use App\Models\Competition;
use App\Models\NewsArticle;
use App\Models\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * بنية الرئيسية من مرجع المدير التنفيذي (قرار صفوان، 30 أكتوبر 2026 — plans/2026-10-30-director-home-reference.md) من
 * بيانات البوابة وحدها: صور العارض، وشرائح الهوية، ومجموع جوائز البراهين، والمستجدات. ولا نص ولا رقم ولا صورة من المرجع.
 */
final class HomeShowcase
{
    /**
     * شرائح البطل بالترتيب المعتمد (أمر التصور النهائي، النسخة 63 — البند H1): تعريف الجمعية، ثم خادم الحرمين الشريفين، ثم رئيس
     * الجمعية، ثم مسابقة البراهين (متى نُشرت)، ثم مكتبة إعجاز. والنصوص من «إعدادات الموقع» و«محتوى الرئيسية» وبيانات المسابقة،
     * فلا نص جديد. وصورة كل شريحة من «محتوى الرئيسية» (شرائح البطل)، وإلا: صورة البطل من الإعدادات ثم أحدث ألبوم منشور للأولى،
     * وصورتا المقطعين للثانية والثالثة، والرسم المؤقت المحايد لما سواها. والأولى بنسختي WebP (HeroImage) لسرعة أول عرض.
     *
     * @param  array<string, mixed>  $home  Setting::group('home')
     * @param  array<string, mixed>  $content  HomeContent::resolved()
     * @param  array{title: string, text: string, button: array{label: string, url: string}|null}  $hero
     * @return list<array{key: string, src: string, srcset: ?string, alt: string, title: string, subtitle: ?string, text: ?string, button: ?array{label: string, url: string}, portrait?: bool}>
     */
    public static function slides(array $home, array $content, array $hero): array
    {
        // بلا صورة: نقش محايد لكل شريحة (صفحتها)، فلا تتكرر صورة واحدة (NeutralImage)
        $placeholder = fn (string $key): array => ['src' => asset(NeutralImage::DIRECTORY.'/'.(['brahin' => 'competitions', 'library' => 'library'][$key] ?? 'default').'.svg'), 'srcset' => null];
        $image = fn (string $key, ?array $fallback, string $alt): array => ($path = HomeContent::heroSlideImage($key)) !== null
            ? [...HeroImage::sources($path), 'alt' => $alt]
            : [...($fallback ?? $placeholder($key)), 'alt' => $fallback !== null ? $alt : ''];

        $short = (string) __('site.short_name');
        $title = $hero['title'];
        // «جمعية إعجاز» سطراً أول، والبقية («للدراسات والبحوث العلمية») سطراً ثانياً، متى كان العنوان اسم الجمعية
        $rest = str_starts_with($title, $short) ? trim(mb_substr($title, mb_strlen($short))) : '';

        $heroImage = is_string($home['hero_image'] ?? null) && trim($home['hero_image']) !== '' ? trim($home['hero_image']) : null;
        $aboutImage = $heroImage !== null ? HeroImage::sources($heroImage) : null;
        $aboutAlt = $heroImage !== null && is_string($home['hero_image_alt'] ?? null) ? trim($home['hero_image_alt']) : '';

        if ($aboutImage === null && ($photo = Album::query()->published()->newestFirst()->first()?->photoList()[0] ?? null) !== null) {
            $aboutImage = HeroImage::sources($photo);
        }

        $slides = [[
            'key' => 'about', ...$image('about', $aboutImage, $aboutAlt),
            'title' => $rest !== '' ? $short : $title, 'subtitle' => $rest !== '' ? $rest : null, 'text' => $hero['text'], 'button' => $hero['button'],
        ], [
            'key' => 'king', ...$image('king', ['src' => (string) $content['king']['image'], 'srcset' => null], (string) $content['king']['image_alt']),
            'title' => (string) $content['king']['signature_title'], 'subtitle' => (string) $content['king']['signature_name'],
            'text' => (string) $content['king']['text'], 'button' => null,
            // صورة المقطع الطولية في إطار بجانب النص، لا ممدودة بعرض البطل (ملاحظة صفوان، 1 نوفمبر 2026)، ما لم تُرفع صورة عريضة للشريحة
            'portrait' => HomeContent::heroSlideImage('king') === null,
        ], [
            'key' => 'chairman', ...$image('chairman', ['src' => (string) $content['chairman']['image'], 'srcset' => null], (string) $content['chairman']['image_alt']),
            'title' => (string) $content['chairman']['name'], 'subtitle' => (string) $content['chairman']['title'],
            'text' => HomeContent::paragraphs((string) $content['chairman']['excerpt'])[1] ?? null, 'button' => null,
            'portrait' => HomeContent::heroSlideImage('chairman') === null,
        ]];

        $competition = self::studentCompetition();

        if ($competition !== null) {
            $url = route('competitions.show', $competition->slug);
            $slides[] = [
                'key' => 'brahin', ...$image('brahin', null, $competition->title),
                'title' => $competition->title, 'subtitle' => $competition->pageText('season'), 'text' => null,
                'button' => ['label' => (string) __('ui.prize.cta'), 'url' => $url],
            ];
        }

        $slides[] = [
            'key' => 'library', ...$image('library', null, (string) __('library.title')),
            'title' => (string) __('library.title'), 'subtitle' => null, 'text' => null,
            'button' => ['label' => (string) __('ui.hero.library'), 'url' => route('library.index')],
        ];

        return $slides;
    }

    /**
     * مسابقة البراهين المنشورة: مسابقة الطلاب الأحدث، أو null.
     */
    private static function studentCompetition(): ?Competition
    {
        return Competition::query()->published()->where('student_mode', true)->latest('id')->first();
    }

    /**
     * شرائح الهوية بترتيبها: الرؤية، ثم الرسالة، ثم القيم واحدة واحدة بأيقوناتها. والفارغة لا شريحة لها. والرؤية والرسالة
     * من «محتوى الرئيسية»، وإلا من مقطعيهما في «عن الجمعية» المنشورة (نصها المعتمد).
     *
     * @param  array<string, mixed>  $content  HomeContent::resolved()
     * @return list<array{kind: string, title: string, text: string, icon: string}>
     */
    public static function identity(array $content): array
    {
        $slides = [];
        $about = null;

        foreach (['vision' => 'eye', 'mission' => 'paper-airplane'] as $key => $icon) {
            $text = trim((string) ($content['identity'][$key] ?? ''));

            if ($text === '') {
                $about ??= self::aboutSections();
                $text = $about[(string) __('ui.identity.'.$key)] ?? '';
            }

            if ($text !== '') {
                $slides[] = ['kind' => $key, 'title' => (string) __('ui.identity.'.$key), 'text' => $text, 'icon' => $icon];
            }
        }

        foreach ((array) ($content['values']['items'] ?? []) as $value) {
            $title = trim((string) ($value['title'] ?? ''));

            if ($title !== '') {
                $slides[] = ['kind' => 'value', 'title' => $title, 'text' => trim((string) ($value['text'] ?? '')), 'icon' => (string) ($value['icon'] ?? 'star')];
            }
        }

        return $slides;
    }

    /**
     * مقاطع النص في «عن الجمعية» المنشورة: العنوان ← النص بلا وسوم.
     *
     * @return array<string, string>
     */
    private static function aboutSections(): array
    {
        $page = Page::query()->published()->where('slug', Page::ABOUT)->first();
        $sections = [];

        foreach (ContentBlocks::visible($page->blocks ?? []) as $block) {
            if ($block['type'] === 'text' && is_string($block['data']['heading'] ?? null)) {
                $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace('</p>', "</p>\n", (string) ($block['data']['body'] ?? ''))))) ?? '');

                if ($text !== '') {
                    $sections[trim($block['data']['heading'])] = $text;
                }
            }
        }

        return $sections;
    }

    /**
     * مسابقة البراهين المنشورة (مسابقة الطلاب الأحدث) ومجموع جوائزها من جدولها، أو null بلا مسابقة أو بلا جوائز. ومعها ما تعرضه
     * شريحتها في «إعلانات» (ملاحظة صفوان ٣٠): حالتها، ومقدمتها من نصوص صفحتها (أو وصفها)، وموعد الفتح أو الإغلاق بحسب الحالة،
     * وصورة شريحتها في البطل إن رُفعت. وكلها من بيانات المسابقة، فلا نص جديد.
     *
     * @return array{title: string, total: int, url: string, status: CompetitionStatus, text: ?string, date: Carbon, image: ?string}|null
     */
    public static function prize(): ?array
    {
        $competition = self::studentCompetition();

        if ($competition === null) {
            return null;
        }

        $total = self::prizeTotal($competition);

        if ($total <= 0) {
            return null;
        }

        $status = $competition->status();
        // وسوم الكتل مسافة قبل نزعها، فلا تلتصق كلمتا فقرتين
        $description = preg_replace('/<\/(p|li|h\d)>|<br\s*\/?>/i', ' ', (string) $competition->description) ?? '';
        $text = $competition->pageText('intro') ?? trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($description))) ?? '');

        return [
            'title' => $competition->title,
            'total' => $total,
            'url' => route('competitions.show', $competition->slug),
            'status' => $status,
            'text' => $text !== '' ? $text : null,
            // قبل الفتح موعده، وبعده موعد الإغلاق
            'date' => $status === CompetitionStatus::Upcoming ? $competition->opens_at : $competition->closes_at,
            'image' => HomeContent::heroSlideUrl('brahin'),
        ];
    }

    /**
     * شرائح دوّار «إعلانات» بالرئيسية (ملاحظة صفوان ٣٠): مسابقة البراهين أولاً ثم إعلانات اللوحة، بما تعرضه الشريحة
     * (home/partials/feed-slide). وبلا صورة غلاف بهوية الجمعية (نقش الصفحة ورمز الشعار). وموعد المسابقة بساعته لأن الإغلاق
     * قد يكون منتصف الليل، وتاريخ الإعلان يومه بلا ساعة.
     *
     * @param  array{title: string, total: int, url: string, status: CompetitionStatus, text: ?string, date: Carbon, image: ?string}|null  $prize
     * @param  iterable<Announcement>  $announcements
     * @return list<array{image: string, cover: bool, badge: string, title: string, text: ?string, date: ?Carbon, date_label: string, with_time: bool, total: ?int, href: ?string, external: bool, cta: string}>
     */
    public static function feed(?array $prize, iterable $announcements): array
    {
        $slides = [];

        if ($prize !== null) {
            $slides[] = [
                'image' => $prize['image'] ?? NeutralImage::forPage('competitions.show'),
                'cover' => $prize['image'] === null,
                'badge' => $prize['status']->registrationLabel(),
                'title' => $prize['title'],
                'text' => $prize['text'],
                'date' => $prize['date'],
                'date_label' => (string) __('ui.feed.date.'.$prize['status']->value),
                'with_time' => true,
                'total' => $prize['total'],
                'href' => $prize['url'],
                'external' => false,
                'cta' => (string) __($prize['status'] === CompetitionStatus::Open ? 'ui.feed.register' : 'ui.feed.details'),
            ];
        }

        foreach ($announcements as $announcement) {
            $slides[] = [
                'image' => filled($announcement->image) ? Storage::disk('public')->url($announcement->image) : NeutralImage::forPage('media.announcements'),
                'cover' => blank($announcement->image),
                'badge' => $announcement->kind->getLabel(),
                'title' => $announcement->title,
                'text' => filled($announcement->summary) ? $announcement->summary : null,
                // «يظهر من» إن حُدد، وإلا يوم إضافته: تاريخ الإعلان لا تاريخ حدث
                'date' => $announcement->starts_on ?? $announcement->created_at,
                'date_label' => (string) __('ui.feed.announced'),
                'with_time' => false,
                'total' => null,
                'href' => $announcement->href(),
                'external' => $announcement->opensExternally(),
                'cta' => (string) __('ui.feed.details'),
            ];
        }

        return $slides;
    }

    /**
     * حالة شريحة البراهين محسوبة وقت رسم الرئيسية المخزّنة ساعة (ContentCache)، فحين يعبر الوقت موعد فتح مسابقة طلاب منشورة أو
     * إغلاقها تُمسح الرئيسية، فلا تبقى «قريباً» بعد الفتح ولا «سجّل الآن» بعد الإغلاق (المجدول كل دقيقة — routes/console.php).
     */
    public static function forgetHomeAtCompetitionBoundary(Carbon $since): bool
    {
        $now = now();
        $crossed = Competition::query()->published()->where('student_mode', true)
            ->where(fn (Builder $query) => $query->whereBetween('opens_at', [$since, $now])->orWhereBetween('closes_at', [$since, $now]))
            ->exists();

        if ($crossed) {
            ContentCache::forget('home');
        }

        return $crossed;
    }

    /**
     * مجموع جوائز المراكز للجنسين.
     */
    public static function prizeTotal(Competition $competition): int
    {
        return array_sum(array_map(fn (array $row): int => max(0, $row['male']) + max(0, $row['female']), $competition->prizeRows()));
    }

    /**
     * الخبر «جديد»: نُشر في الأيام السبعة الأخيرة.
     */
    public static function isNew(NewsArticle $article): bool
    {
        return $article->published_on->gte(now()->subDays(7)->startOfDay());
    }
}
