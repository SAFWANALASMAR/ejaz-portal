<?php

namespace App\Support;

/**
 * مقاطع المحتوى المرنة (Filament Builder): نص، وبطاقات، وصورة. تُخزَّن JSON في الصفحات
 * والمبادرات، وهذه الدوال تقرؤها بأمان لأي شكل وصل إلى القاعدة.
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
     * مسارات صور المقاطع، لتنظيف الجديدة وحذف المحذوفة.
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
        }

        return $images;
    }
}
