<?php

namespace App\Support;

use App\Enums\OpeningKind;
use App\Models\Achievement;
use App\Models\Album;
use App\Models\Announcement;
use App\Models\Competition;
use App\Models\Opening;
use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

/**
 * أساس محركات البحث في التخطيط العام وخريطة الموقع (feat/seo-foundation — قرار صفوان، 11 نوفمبر 2026):
 * وصف لكل صفحة قائمة، وعنوان بلا لاحقة الجمعية إن طال، وصفحات القوائم الفارغة خارج الفهرسة.
 */
final class Seo
{
    public const DESCRIPTION_LENGTH = 160;

    public const TITLE_LENGTH = 65;

    /** قوائم تخرج من الفهرسة وخريطة الموقع ما دامت فارغة، وتعود بأول عنصر منشور */
    public const LISTINGS = ['media.albums.index', 'media.announcements', 'achievements.index', 'services.jobs', 'services.volunteering', 'competitions.index'];

    /**
     * وصف الصفحة من lang/ar/seo.php باسم مسارها، أو null فيبقى الوصف العام. ووصف فيه :title يُقص عنوانه وحده ليبقى
     * الوصف كله في 160 حرفاً بنقطته.
     */
    public static function description(?Route $route, ?string $title): ?string
    {
        $name = $route?->getName();

        if ($name === null) {
            return null;
        }

        $section = $route->parameter('section');
        $key = 'seo.descriptions.'.str_replace('.', '_', $name).($name === 'library.section' ? '_'.($section instanceof BackedEnum ? $section->value : (string) $section) : '');

        if (! Lang::has($key)) {
            return null;
        }

        $room = self::DESCRIPTION_LENGTH - mb_strlen(__($key, ['title' => '']));
        $title = (string) $title;

        return __($key, ['title' => mb_strlen($title) <= $room ? $title : Str::limit($title, max(1, $room - 1), '…')]);
    }

    /**
     * العنوان بلاحقة « | جمعية إعجاز» ما لم يتجاوز بها 65 حرفاً، فيُكتفى بالعنوان.
     */
    public static function title(string $title): string
    {
        $full = $title.' | '.__('site.short_name');

        return mb_strlen($full) > self::TITLE_LENGTH ? $title : $full;
    }

    /**
     * القائمة فارغة الآن، في ذاكرة المحتوى فيمسحها نشر أي عنصر (FlushesContentCache)، ومدتها ساعة لما يفرغ بمرور الوقت
     * (إعلان انتهى، أو فرصة أُغلقت). ومفتاح لكل قائمة، فصفحتها تسأل عنها وحدها.
     */
    public static function isEmptyListing(?string $route): bool
    {
        return in_array($route, self::LISTINGS, true)
            && ContentCache::remember('empty-listing.'.$route, fn (): bool => ! self::listing($route)->exists());
    }

    /**
     * @return list<string>
     */
    public static function emptyListings(): array
    {
        return array_values(array_filter(self::LISTINGS, self::isEmptyListing(...)));
    }

    /**
     * @return Builder<covariant \Illuminate\Database\Eloquent\Model>
     */
    private static function listing(string $route): Builder
    {
        // الاستعلام نفسه الذي تعرضه القائمة في متحكمها
        return match ($route) {
            'media.albums.index' => Album::query()->published(),
            'media.announcements' => Announcement::query()->current(),
            'achievements.index' => Achievement::query()->timeline(),
            'services.jobs', 'services.volunteering' => Opening::query()->published()
                ->where('kind', ($route === 'services.jobs' ? OpeningKind::Employment : OpeningKind::Volunteering)->value)
                ->where(fn ($query) => $query->whereNull('closes_at')->orWhere('closes_at', '>', now())),
            'competitions.index' => Competition::query()->published(),
        };
    }
}
