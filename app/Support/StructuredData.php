<?php

namespace App\Support;

use App\Enums\LibrarySection;
use App\Enums\ProgramDelivery;
use App\Models\Competition;
use App\Models\LibraryItem;
use App\Models\NewsArticle;
use App\Models\Program;
use App\Models\Setting;
use App\Rules\SafeUrl;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * البيانات المنظمة schema.org (JSON-LD) لمحركات البحث: الجمعية في كل صفحة، ومسار التنقل في الصفحات الداخلية،
 * والفعالية للبرنامج والمسابقة، والكتاب والمرئية في المكتبة. القيم من القاعدة والإعدادات وحدها، وما لم يُدخل
 * لا يُكتب، ولا يُبنى إلا لمنشور (المتحكمات تجلب المنشور وحده). ويُرسم بـ script() داخل @push('head').
 */
class StructuredData
{
    /**
     * أقصى طول للوصف في البيانات المنظمة.
     */
    private const DESCRIPTION_LIMIT = 300;

    /**
     * كتلة JSON-LD. والرموز < و> و& تُهرَّب داخل النص (JSON_HEX_*)، فلا يُغلق عنوانٌ فيه </script> الكتلة.
     * وكتلة البيانات لا تُنفَّذ فلا تمنعها CSP، والرمز (nonce) عليها للاتساق مع سياسة الموقع (decisions.md).
     *
     * @param  array<string, mixed>|list<array<string, mixed>>  $data  عقدة واحدة أو قائمة عقد
     */
    public static function script(array $data): HtmlString
    {
        $json = json_encode(
            array_is_list($data) ? ['@context' => 'https://schema.org', '@graph' => $data] : ['@context' => 'https://schema.org', ...$data],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR,
        );

        // ولا يُقارن Livewire الرمز عند دمج الرأس في التنقّل السريع، فلا تتكرر كتلة الجمعية بتغيّره
        $nonce = Vite::cspNonce();

        return new HtmlString('<script type="application/ld+json"'.($nonce !== null ? ' nonce="'.e($nonce).'"' : '').'>'.$json.'</script>');
    }

    /**
     * الجمعية: الاسم والرابط والشعار، والحسابات الرسمية والبريد من «إعدادات الموقع» متى أُدخلت.
     *
     * @return array<string, mixed>
     */
    public static function organization(): array
    {
        $contact = Setting::group('contact');
        $social = array_values(array_filter(
            (array) ($contact['social'] ?? []),
            fn ($url): bool => is_string($url) && SafeUrl::isSafe($url, allowInternal: false),
        ));
        $email = is_string($contact['email'] ?? null) && filter_var($contact['email'], FILTER_VALIDATE_EMAIL) ? $contact['email'] : null;

        return self::filled([
            '@type' => 'Organization',
            '@id' => url('/').'#organization',
            'name' => __('site.name'),
            'alternateName' => __('site.short_name'),
            'url' => url('/'),
            'logo' => asset(ShareImage::DEFAULT),
            'sameAs' => $social,
            'email' => $email,
        ]);
    }

    /**
     * الموقع في الرئيسية وحدها: اسمه الكامل ليظهر في نتائج البحث اسماً للموقع، بلا اسم مختصر بديل
     * (decisions/2026-11-10-seo-site-name.md).
     *
     * @return array<string, mixed>
     */
    public static function website(): array
    {
        return [
            '@type' => 'WebSite',
            '@id' => url('/').'#website',
            'name' => __('site.name'),
            'url' => url('/'),
            'inLanguage' => 'ar',
            'publisher' => ['@id' => url('/').'#organization'],
        ];
    }

    /**
     * مسار التنقل: الرئيسية، ثم الأقسام الأعلى، ثم الصفحة نفسها.
     *
     * @param  list<array{0: string, 1: string}>  $parents  [الاسم، الرابط] من الأعلى إلى الأدنى
     * @return array<string, mixed>
     */
    public static function breadcrumbs(string $title, string $url, array $parents = []): array
    {
        $trail = [[__('site.home'), route('home')], ...$parents, [$title, $url]];

        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(fn (array $crumb, int $index): array => [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $crumb[0],
                'item' => $crumb[1],
            ], $trail, array_keys($trail)),
        ];
    }

    /**
     * البرنامج فعاليةً: الموعدان، والحضور أو عن بعد بموقعه، والجهة المنظِّمة. ورابط الاجتماع لا يُكتب (للمسجل
     * المؤكد وحده)، فمكان الحضور عن بعد صفحة البرنامج نفسها. والحضوري بلا موقع مُدخل لا فعالية له، لأن Google
     * يشترط المكان.
     *
     * @return array<string, mixed>|null
     */
    public static function program(Program $program): ?array
    {
        $url = route('programs.show', $program->slug);
        $place = filled($program->location) ? ['@type' => 'Place', 'name' => $program->location, 'address' => $program->location] : null;
        $virtual = ['@type' => 'VirtualLocation', 'url' => $url];

        if ($program->delivery === ProgramDelivery::InPerson && $place === null) {
            return null;
        }

        return self::filled([
            '@type' => 'Event',
            'name' => $program->title,
            'description' => self::text($program->summary ?: $program->description),
            'url' => $url,
            'image' => $program->image ? Storage::disk('public')->url($program->image) : null,
            'startDate' => self::date($program->starts_at),
            'endDate' => self::date($program->ends_at),
            'eventStatus' => 'https://schema.org/EventScheduled',
            'eventAttendanceMode' => match ($program->delivery) {
                ProgramDelivery::InPerson => 'https://schema.org/OfflineEventAttendanceMode',
                ProgramDelivery::Remote => 'https://schema.org/OnlineEventAttendanceMode',
                ProgramDelivery::Hybrid => 'https://schema.org/MixedEventAttendanceMode',
            },
            'location' => match ($program->delivery) {
                ProgramDelivery::InPerson => $place,
                ProgramDelivery::Remote => $virtual,
                ProgramDelivery::Hybrid => array_values(array_filter([$place, $virtual])),
            },
            'organizer' => self::organizer(),
        ]);
    }

    /**
     * المسابقة فعاليةً على البوابة: من فتحها إلى إغلاقها، والمشاركة من صفحتها. وإغلاق مسابقة الطلاب نهاية التسجيل
     * لا نهاية فروعها، فلا نهاية لها هنا.
     *
     * @return array<string, mixed>
     */
    public static function competition(Competition $competition): array
    {
        $url = route('competitions.show', $competition->slug);

        return self::filled([
            '@type' => 'Event',
            'name' => $competition->title,
            'description' => self::text($competition->description),
            'url' => $url,
            'startDate' => self::date($competition->opens_at),
            'endDate' => $competition->student_mode ? null : self::date($competition->closes_at),
            'eventStatus' => 'https://schema.org/EventScheduled',
            'eventAttendanceMode' => 'https://schema.org/OnlineEventAttendanceMode',
            'location' => ['@type' => 'VirtualLocation', 'url' => $url],
            'organizer' => self::organizer(),
        ]);
    }

    /**
     * عنصر المكتبة: الكتاب Book، والبحث ScholarlyArticle، والمرئية VideoObject بصورتها المخزّنة ورابط تضمينها. والصوتية لا شيء، ولا المرئية
     * بلا صورة أو تاريخ نشر (يشترطهما Google، ولا يُخترع لهما بديل).
     *
     * @return array<string, mixed>|null
     */
    public static function libraryItem(LibraryItem $item): ?array
    {
        $url = route('library.show', [$item->section->value, $item->slug]);
        $thumbnail = $item->thumbnailUrl();

        // والبحث ScholarlyArticle بعنوانه headline كما يطلبه Google للمقالات (البند R9: «الأبحاث والدراسات» قسم مستقل)
        if ($item->section->takesPdf()) {
            return self::filled([
                '@type' => $item->section === LibrarySection::Books ? 'Book' : 'ScholarlyArticle',
                'headline' => $item->section === LibrarySection::Research ? $item->title : null,
                'name' => $item->title,
                'description' => self::text($item->description),
                'url' => $url,
                'image' => $thumbnail,
                'author' => filled($item->author) ? ['@type' => 'Person', 'name' => $item->author] : null,
                'datePublished' => $item->published_on?->toDateString(),
                'inLanguage' => 'ar',
                'publisher' => self::organizer(),
            ]);
        }

        if ($item->section !== LibrarySection::Videos) {
            return null;
        }

        if ($thumbnail === null || $item->published_on === null) {
            return null;
        }

        $youtube = $item->youtubeId();

        return self::filled([
            '@type' => 'VideoObject',
            'name' => $item->title,
            'description' => self::text($item->description) ?? $item->title,
            'url' => $url,
            'thumbnailUrl' => $thumbnail,
            'uploadDate' => $item->published_on->toDateString(),
            'embedUrl' => $youtube !== null ? 'https://www.youtube-nocookie.com/embed/'.$youtube : null,
            'contentUrl' => $youtube === null && filled($item->video_url) && SafeUrl::isSafe($item->video_url, allowInternal: false) ? $item->video_url : null,
            'inLanguage' => 'ar',
        ]);
    }

    /**
     * الخبر NewsArticle: العنوان والملخص وصورة الغلاف وتاريخ النشر، والناشر والمؤلف الجمعية (لا كاتب مُدخل للخبر).
     * وتعديله آخر حفظ له.
     *
     * @return array<string, mixed>
     */
    public static function newsArticle(NewsArticle $article): array
    {
        $url = route('media.news.show', $article->slug);

        return self::filled([
            '@type' => 'NewsArticle',
            'headline' => Str::limit($article->title, 110),
            'description' => self::text($article->summary),
            'url' => $url,
            'mainEntityOfPage' => $url,
            'image' => $article->image ? Storage::disk('public')->url($article->image) : null,
            'datePublished' => $article->published_on->toDateString(),
            'dateModified' => self::date($article->updated_at),
            'inLanguage' => 'ar',
            'author' => self::organizer(),
            'publisher' => [...self::organizer(), 'logo' => ['@type' => 'ImageObject', 'url' => asset(ShareImage::DEFAULT)]],
        ]);
    }

    /**
     * @return array<string, string>
     */
    private static function organizer(): array
    {
        return ['@type' => 'Organization', 'name' => __('site.name'), 'url' => url('/')];
    }

    /**
     * التاريخ بتوقيت العرض (الرياض) بصيغة ISO 8601 بفرق التوقيت.
     */
    private static function date(?CarbonInterface $date): ?string
    {
        return $date?->copy()->setTimezone((string) config('app.display_timezone'))->toIso8601String();
    }

    /**
     * نص مسطّح بلا وسوم ولا أسطر، بحد أقصى. ومنه وصف الصفحة (LibraryController).
     */
    public static function text(?string $text, int $limit = self::DESCRIPTION_LIMIT): ?string
    {
        $plain = trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) $text)));

        return $plain === '' ? null : Str::limit($plain, $limit);
    }

    /**
     * بلا القيم الفارغة، فلا يُكتب حقل لم يُدخل.
     *
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private static function filled(array $node): array
    {
        return array_filter($node, fn ($value): bool => $value !== null && $value !== [] && $value !== '');
    }
}
