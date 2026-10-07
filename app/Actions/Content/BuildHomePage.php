<?php

namespace App\Actions\Content;

use App\Enums\SiteIcon;
use App\Models\Initiative;
use App\Models\Setting;
use App\Rules\SafeUrl;
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
        'media' => ['icon' => 'megaphone', 'route' => 'media.index'],
    ];

    /**
     * @return array{
     *     hero: array{title: string, text: string, image: string, button: array{label: string, url: string}|null},
     *     stats: list<array{label: string, value: int, suffix: string, icon: string|null}>,
     *     shortcuts: list<array{key: string, icon: string, url: string}>,
     *     initiatives: Collection<int, Initiative>,
     * }
     */
    public function handle(): array
    {
        $home = Setting::group('home');

        return [
            'hero' => self::hero($home),
            'stats' => self::shows($home, 'stats') ? self::stats($home) : [],
            'shortcuts' => self::shows($home, 'shortcuts') ? self::shortcuts() : [],
            // ثلاث بطاقات كما في الصورة 07، والبقية في صفحة المبادرات
            'initiatives' => self::shows($home, 'initiatives') ? Initiative::query()->published()->limit(3)->get() : new Collection,
        ];
    }

    /**
     * المقطع ظاهر ما لم يُعطَّل صراحة من الإعدادات.
     *
     * @param  array<string, mixed>  $home
     */
    private static function shows(array $home, string $section): bool
    {
        return (bool) ($home['show_'.$section] ?? true);
    }

    /**
     * @param  array<string, mixed>  $home
     * @return array{title: string, text: string, image: string, button: array{label: string, url: string}|null}
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
     * @return list<array{key: string, icon: string, url: string}>
     */
    private static function shortcuts(): array
    {
        $shortcuts = [];

        foreach (self::SHORTCUTS as $key => $shortcut) {
            if (Route::has($shortcut['route'])) {
                $shortcuts[] = ['key' => $key, 'icon' => $shortcut['icon'], 'url' => route($shortcut['route'])];
            }
        }

        return $shortcuts;
    }
}
