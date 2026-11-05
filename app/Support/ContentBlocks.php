<?php

namespace App\Support;

/**
 * مقاطع المحتوى المرنة (Filament Builder): نص، وبطاقات (لكل بطاقة أيقونة أو صورة)، وصورة.
 * تُخزَّن JSON في الصفحات والمبادرات، وهذه الدوال تقرؤها بأمان لأي شكل وصل إلى القاعدة.
 */
final class ContentBlocks
{
    public const TYPES = ['text', 'cards', 'image'];

    /**
     * المقاطع المعروفة بترتيبها، بلا مفاتيح Filament الداخلية.
     *
     * @return list<array{type: string, data: array<string, mixed>}>
     */
    public static function normalize(mixed $blocks): array
    {
        $normalized = [];

        foreach (is_array($blocks) ? $blocks : [] as $block) {
            if (is_array($block) && in_array($block['type'] ?? null, self::TYPES, true) && is_array($block['data'] ?? null)) {
                $normalized[] = ['type' => $block['type'], 'data' => $block['data']];
            }
        }

        return $normalized;
    }

    /**
     * المقاطع الظاهرة للعرض: بلا ما أُطفئ مفتاح إظهاره في «محرر الصفحات» (data.visible). وما بلا المفتاح ظاهر. وصور المخفي
     * تبقى في images()، فلا يحذفها حفظ ولا أمر تنظيف.
     *
     * @return list<array{type: string, data: array<string, mixed>}>
     */
    public static function visible(mixed $blocks): array
    {
        return array_values(array_filter(self::normalize($blocks), fn (array $block): bool => ($block['data']['visible'] ?? true) !== false));
    }

    /**
     * أقصى طول لنص المقطع (بلا وسوم) ليدخل البطاقات المرقمة المتراكمة. الأطول (سياسة أو لائحة) يبقى مقطعاً عادياً.
     */
    public const STACK_TEXT = 700;

    /**
     * المقاطع للعرض: مقاطع النص القصيرة المتتالية بعناوينها (اثنان فأكثر) بطاقات مرقمة متراكمة (stack) كما في الموقع السابق
     * («من نحن» و«الرؤية» و«الرسالة» — الدفعة الثانية من التحسين البصري)، والبقية كما هي.
     *
     * @return list<array{type: string, data: array<string, mixed>}>
     */
    public static function grouped(mixed $blocks): array
    {
        $grouped = [];
        $run = [];

        foreach (self::normalize($blocks) as $block) {
            $body = is_string($block['data']['body'] ?? null) ? $block['data']['body'] : '';
            $heading = $block['data']['heading'] ?? null;

            if ($block['type'] === 'text' && is_string($heading) && trim($heading) !== '' && mb_strlen(trim(strip_tags($body))) <= self::STACK_TEXT) {
                $run[] = $block;

                continue;
            }

            $grouped = [...$grouped, ...self::stack($run), $block];
            $run = [];
        }

        return [...$grouped, ...self::stack($run)];
    }

    /**
     * @param  list<array{type: string, data: array<string, mixed>}>  $run
     * @return list<array{type: string, data: array<string, mixed>}>
     */
    private static function stack(array $run): array
    {
        return count($run) >= 2 ? [['type' => 'stack', 'data' => ['items' => array_column($run, 'data')]]] : $run;
    }

    /**
     * مسارات صور المقاطع وصور البطاقات، لتنظيف الجديدة وحذف المحذوفة.
     *
     * @return list<string>
     */
    public static function images(mixed $blocks): array
    {
        $images = [];

        foreach (self::normalize($blocks) as $block) {
            $path = $block['data']['picture'] ?? null;

            if ($block['type'] === 'image' && is_string($path) && $path !== '') {
                $images[] = $path;
            }

            foreach ($block['type'] === 'cards' ? (array) ($block['data']['items'] ?? []) : [] as $item) {
                $picture = is_array($item) ? ($item['picture'] ?? null) : null;

                if (is_string($picture) && $picture !== '') {
                    $images[] = $picture;
                }
            }
        }

        return $images;
    }

    /**
     * مرسى المقطع في «عن الجمعية» الذي تفتحه بطاقته في الرئيسية وقائمته المنسدلة (ملاحظات المدير التنفيذي، 1 نوفمبر 2026، البند ٨):
     * الرؤية والرسالة والأهداف والقيم والمبادئ، بعنوان المقطع كلمةً وحدها أو متبوعة بلاحقة معتمدة («الأهداف» و«أهداف الجمعية» — رابط
     * العرض، 5 نوفمبر 2026)، لا كل عنوان أوله الكلمة («رؤية 2030» و«رسالة رئيس المجلس» و«القيم المضافة» بلا مرسى — قرار المشرف
     * على طلب الدمج 183). وتُحذف علامات الترقيم الختامية، وتُوحَّد الهمزة («اهداف الجمعية»). والكلمات واللواحق في lang/ar/ui.php.
     */
    public static function anchor(mixed $heading): ?string
    {
        $normalize = fn (string $text): string => strtr((string) preg_replace('/\s+/u', ' ', trim($text)), ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا']);
        $heading = $normalize((string) preg_replace('/[\s.:،؛,;!؟?\-–—]+$/u', '', is_string($heading) ? $heading : ''));

        if ($heading === '') {
            return null;
        }

        $suffixes = array_map($normalize, (array) __('ui.about_anchors.suffixes'));

        foreach ((array) __('ui.about_anchors.words') as $anchor => $words) {
            foreach (array_map($normalize, (array) $words) as $word) {
                if ($heading === $word || in_array($heading, array_map(fn (string $suffix): string => $word.' '.$suffix, $suffixes), true)) {
                    return 'about-'.$anchor;
                }
            }
        }

        return null;
    }

    /**
     * المرسى لأول عنوان مطابق في الصفحة وحده، فلا يتكرر المعرّف إن تشابه عنوانان (قرار المشرف على طلب الدمج 183): القوالب تستدعي
     * هذه، والصفحة الواحدة طلب واحد (ما عُرض في الطلب يُحفظ في سماته).
     */
    public static function anchorOnce(mixed $heading): ?string
    {
        $anchor = self::anchor($heading);

        if ($anchor === null) {
            return null;
        }

        /** @var list<string> $used */
        $used = (array) request()->attributes->get('about_anchors', []);

        if (in_array($anchor, $used, true)) {
            return null;
        }

        request()->attributes->set('about_anchors', [...$used, $anchor]);

        return $anchor;
    }
}
