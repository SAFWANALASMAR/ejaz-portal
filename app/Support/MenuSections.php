<?php

namespace App\Support;

use App\Enums\DocumentCategory;
use App\Enums\LibrarySection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

/**
 * أقسام القوائم المنسدلة في الرأس من البيانات والمسارات القائمة لا من نص مكتوب (ملاحظات المدير التنفيذي، 1 نوفمبر 2026،
 * البند ٤): أول عنصر الصفحة الأم نفسها، ثم أقسامها الفعلية. مكتبة إعجاز بأقسامها الأربعة، والمركز الإعلامي بأقسامه كلها
 * (ومنها مكتبة الفيديو وقياس الرضا)، والحوكمة بتصنيفاتها الخمسة، والخدمات الإلكترونية
 * بخدماتها المتاحة (config/eservices.php، الشرط نفسه في صفحتها). وما أضافه المحرر من عناصر فرعية في «القائمة» يبقى بعدها.
 */
final class MenuSections
{
    /**
     * أقسام عنصر رئيسي بمسار رابطه، أو مصفوفة فارغة لعنصر ليس من الأربعة.
     *
     * @return list<array{label: string, url: string}>
     */
    public static function for(string $url): array
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');

        return match ($path) {
            'library' => self::library(),
            'media' => self::media(),
            'governance' => self::governance(),
            'services' => self::services(),
            default => [],
        };
    }

    /**
     * عناصر القائمة المنسدلة: الصفحة الأم أولاً، ثم الأقسام، ثم بقية عناصر المحرر الفرعية كلها.
     *
     * @param  array{label: string, url: ?string, opens_in_new_tab?: bool, children: list<array<string, mixed>>}  $row
     * @return list<array<string, mixed>>
     */
    public static function children(array $row): array
    {
        $sections = self::for((string) $row['url']);
        $children = $row['children'];

        if ($sections === [] && $children === []) {
            return [];
        }

        // عناصر المحرر لا يسقط منها شيء (ولو شارك عنصرٌ الأمَّ رابطها). وأول عنصر برابط الأم هو الأم أولاً ولو سبقته عناصر (مراسي «عن
        // الجمعية» قبله — إضافة توجيه المدير، البند ٢٨)، وإلا أُضيفت الأم أولاً
        $parentKey = self::key((string) $row['url']);
        $parentAt = array_key_first(array_filter($children, fn (array $child): bool => self::key((string) $child['url']) === $parentKey));
        $parent = $parentAt !== null ? array_splice($children, $parentAt, 1)[0] : null;
        $items = $parent !== null
            ? [[...$parent, 'is_parent' => true]]
            : [['id' => 0, 'label' => $row['label'], 'url' => $row['url'], 'opens_in_new_tab' => (bool) ($row['opens_in_new_tab'] ?? false), 'is_parent' => true]];

        // ثم الأقسام بترتيبها، وما كان منها في عناصر المحرر يأخذ عنصره (باسمه) مكانه، ثم بقية عناصر المحرر
        foreach ($sections as $section) {
            $key = self::key($section['url']);

            if ($key === $parentKey) {
                continue;
            }

            $match = array_key_first(array_filter($children, fn (array $child): bool => self::key((string) $child['url']) === $key));

            if ($match !== null) {
                $items[] = $children[$match];
                unset($children[$match]);
            } else {
                $items[] = ['id' => 0, 'label' => $section['label'], 'url' => $section['url'], 'opens_in_new_tab' => false, 'generated' => true];
            }
        }

        return [...$items, ...array_values($children)];
    }

    /**
     * مسار الرابط واستعلامه بلا النطاق ولا الشرطة الأخيرة، للمقارنة وحدها.
     */
    private static function key(string $url): string
    {
        $parts = parse_url($url);
        $key = '/'.trim($parts['path'] ?? '', '/');
        $key = isset($parts['query']) ? $key.'?'.$parts['query'] : $key;

        // المرساة جزء من المفتاح: «/about#about-vision» مقطع من الصفحة لا الصفحة الأم نفسها
        return isset($parts['fragment']) ? $key.'#'.$parts['fragment'] : $key;
    }

    /**
     * @return list<array{label: string, url: string}>
     */
    private static function library(): array
    {
        return array_map(fn (LibrarySection $section): array => [
            'label' => $section->getLabel(),
            'url' => '/library/'.$section->value,
        ], LibrarySection::cases());
    }

    /**
     * @return list<array{label: string, url: string}>
     */
    private static function media(): array
    {
        $items = [
            ['label' => (string) __('media.tiles.news'), 'url' => '/media/news'],
            ['label' => (string) __('media.tiles.announcements'), 'url' => '/media/announcements'],
            ['label' => (string) __('media.tiles.albums'), 'url' => '/media/albums'],
            ['label' => (string) __('media.tiles.videos'), 'url' => '/library/'.LibrarySection::Videos->value],
            ['label' => (string) __('media.tiles.partners'), 'url' => '/media/partners'],
            // «منشوراتنا» قسم من المركز الإعلامي لا عنصر في الصف الأول (المشرف، 4 نوفمبر 2026 — docs/decisions/2026-11-04-social-posts.md)
            ['label' => (string) __('media.tiles.social'), 'url' => '/media/social'],
        ];

        // «قياس الرضا» دائماً، وبلا استبيان مفتوح صفحته برسالتها، والتصنيف بلا وثيقة بحالته الفارغة (قرار صفوان، 11 نوفمبر 2026:
        // «خلي كل شيء بالموقع ظاهر حتى لو ما فيه محتوى» — docs/decisions/2026-11-11-fix-show-empty-sections.md)
        if (Route::has('surveys.index')) {
            $items[] = ['label' => (string) __('media.tiles.surveys'), 'url' => '/surveys'];
        }

        return $items;
    }

    /**
     * @return list<array{label: string, url: string}>
     */
    private static function governance(): array
    {
        return array_map(
            fn (DocumentCategory $category): array => ['label' => (string) $category->getLabel(), 'url' => '/governance?category='.$category->value],
            DocumentCategory::cases(),
        );
    }

    /**
     * @return list<array{label: string, url: string}>
     */
    private static function services(): array
    {
        $items = array_map(fn (array $tile): array => [
            'label' => (string) __($tile['label']),
            'url' => (string) parse_url(route($tile['route']), PHP_URL_PATH),
        ], self::availableServices(cached: true));

        // «متجر الجمعية» آخرها، إلى صفحة «المتجر والتبرعات» في الموقع (ملاحظات المدير، 4 نوفمبر 2026 ليلاً — docs/decisions/2026-11-04-store-page.md).
        // وليس بطاقة في config/eservices.php، لأن صفحة الخدمات فيها بطاقة المتجر برابط سلة (data-store-card)
        $items[] = ['label' => (string) __('store.menu.services_item'), 'url' => (string) parse_url(route('store.index'), PHP_URL_PATH)];

        return $items;
    }

    /**
     * بطاقات «خدماتنا الإلكترونية» المتاحة الآن: وحدة مبنية مسارها، وبشرطها إن كان لها. الشرط نفسه في صفحة الخدمات، وفي الرأس
     * (cached) يُخزَّن الشرط دقيقة.
     *
     * @return list<array{route: string, label: string, icon: string, symbol?: string, wide?: bool, available?: callable(): bool}>
     */
    public static function availableServices(bool $cached = false): array
    {
        /** @var list<array{route: string, label: string, icon: string, symbol?: string, wide?: bool, available?: callable(): bool}> $tiles */
        $tiles = config('eservices.tiles', []);

        return array_values(array_filter($tiles, fn (array $tile): bool => Route::has($tile['route'])
            && (! isset($tile['available']) || ($cached ? self::minute('service.'.$tile['route'], $tile['available']) : (bool) call_user_func($tile['available'])))));
    }

    private static function minute(string $key, callable $check): bool
    {
        return (bool) Cache::remember('menu.'.$key, 60, fn (): bool => (bool) call_user_func($check));
    }
}
