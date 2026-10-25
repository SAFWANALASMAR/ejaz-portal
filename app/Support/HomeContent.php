<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

/**
 * مقاطع الرئيسية من الخطة الاستراتيجية (ملاحظات المدير التنفيذي عبر صفوان، 25 أكتوبر 2026): كلمة خادم الحرمين الشريفين،
 * وكلمة رئيس مجلس الإدارة، والمبررات، والقيم الأربع. نصوصها الأصلية منقولة حرفياً في lang/ar/home.php (sections)،
 * وصورها في public/images/home، وكل نص وصورة يُستبدل من اللوحة («محتوى الرئيسية» — UpdateHomeContent) ويُحفظ في
 * الإعدادات (المفتاح home_sections). فما لم يُعدَّل يبقى على الأصل.
 */
final class HomeContent
{
    public const KEY = 'home_sections';

    /**
     * عدد القيم في الخطة الاستراتيجية (الصفحتان 9 و10).
     */
    public const VALUES = 4;

    /**
     * النصوص لكل مقطع، وما يُكتب في سطور متعددة منها.
     *
     * @var array<string, list<string>>
     */
    public const TEXTS = [
        'king' => ['heading', 'text', 'signature_title', 'signature_name'],
        'chairman' => ['heading', 'excerpt', 'full', 'name', 'title'],
        'rationale' => ['heading', 'wordmark', 'text', 'lead', 'outcome'],
        'values' => ['heading'],
    ];

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
     * المحتوى للعرض: المحفوظ من اللوحة، وإلا الأصل.
     *
     * @return array{
     *     king: array{heading: string, text: string, signature_title: string, signature_name: string, image: string, image_alt: string},
     *     chairman: array{heading: string, excerpt: string, full: string, name: string, title: string, image: string, image_alt: string},
     *     rationale: array{heading: string, wordmark: string, text: string, lead: string, outcome: string, image: string, image_alt: string},
     *     values: array{heading: string, items: list<array{title: string, text: string, image: string}>},
     * }
     */
    public static function resolved(): array
    {
        $stored = Setting::group(self::KEY);
        $content = [];

        foreach (self::TEXTS as $section => $keys) {
            foreach ($keys as $key) {
                $content[$section][$key] = self::text($stored[$section][$key] ?? null) ?? (string) __('home.sections.'.$section.'.'.$key);
            }
        }

        foreach (self::IMAGES as $section => $default) {
            $path = self::text($stored[$section]['image'] ?? null);
            $content[$section]['image'] = $path !== null ? Storage::disk('public')->url($path) : asset($default);
            $content[$section]['image_alt'] = self::text($stored[$section]['image_alt'] ?? null) ?? (string) __('home.sections.'.$section.'.image_alt');
        }

        $content['values']['items'] = [];

        foreach (range(0, self::VALUES - 1) as $index) {
            $item = (array) ($stored['values']['items'][$index] ?? []);
            $path = self::text($item['image'] ?? null);

            $content['values']['items'][] = [
                'title' => self::text($item['title'] ?? null) ?? (string) __('home.sections.values.items.'.$index.'.title'),
                'text' => self::text($item['text'] ?? null) ?? (string) __('home.sections.values.items.'.$index.'.text'),
                'image' => $path !== null ? Storage::disk('public')->url($path) : asset('images/home/value-'.($index + 1).'.jpg'),
            ];
        }

        /** @var array{king: array{heading: string, text: string, signature_title: string, signature_name: string, image: string, image_alt: string}, chairman: array{heading: string, excerpt: string, full: string, name: string, title: string, image: string, image_alt: string}, rationale: array{heading: string, wordmark: string, text: string, lead: string, outcome: string, image: string, image_alt: string}, values: array{heading: string, items: list<array{title: string, text: string, image: string}>}} $content */
        return $content;
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

        return array_values(array_filter($paths, fn (mixed $path): bool => is_string($path) && $path !== ''));
    }

    /**
     * فقرات النص الطويل (كلمة رئيس المجلس كاملة): سطر فارغ أو أكثر بين كل فقرتين.
     *
     * @return list<string>
     */
    public static function paragraphs(string $text): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/u', $text) ?: []), fn (string $line): bool => $line !== ''));
    }

    private static function text(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
