<?php

namespace App\Support;

use BladeUI\Icons\Exceptions\SvgNotFound;
use BladeUI\Icons\Factory;
use BladeUI\Icons\Svg;

/**
 * أيقونات الواجهة العامة ولوحة المستفيد: Phosphor بوزن duotone (طلب صفوان، 29 أكتوبر 2026 — decisions.md). والأسماء في
 * القوالب والتعدادات والإعدادات تبقى أسماء Heroicons كما حُفظت (SiteIcon وicon() في التعدادات)، فلا ترحيل ولا تغيير في
 * اللوحة: يُترجم الاسم هنا. وما لا ترجمة له فاسمه نفسه في Phosphor إن وُجد، وإلا أيقونة Heroicons الأصلية.
 * ولوحة Filament تبقى بأيقوناتها.
 */
final class Icon
{
    /**
     * اسم Heroicons ← اسم Phosphor، لما يختلف اسمه.
     *
     * @var array<string, string>
     */
    private const MAP = [
        'academic-cap' => 'graduation-cap',
        'arrow-down-tray' => 'download-simple',
        'arrow-right-end-on-rectangle' => 'sign-in',
        'arrow-trending-up' => 'trend-up',
        'arrow-right-start-on-rectangle' => 'sign-out',
        'banknotes' => 'money',
        'bars-3' => 'list',
        'beaker' => 'flask',
        'building-library' => 'bank',
        'calendar' => 'calendar-blank',
        'calendar-days' => 'calendar-dots',
        'chat-bubble-bottom-center-text' => 'chat-text',
        'chat-bubble-left-right' => 'chats-circle',
        'check-badge' => 'seal-check',
        'chevron-down' => 'caret-down',
        'chevron-left' => 'caret-left',
        'chevron-right' => 'caret-right',
        'chevron-up' => 'caret-up',
        'clipboard-document-list' => 'clipboard-text',
        'cog-6-tooth' => 'gear',
        'computer-desktop' => 'desktop',
        'document-arrow-down' => 'file-arrow-down',
        'document-text' => 'file-text',
        'envelope' => 'envelope-simple',
        'exclamation-triangle' => 'warning',
        'face-smile' => 'smiley',
        'globe-alt' => 'globe',
        'hand-raised' => 'hand',
        'hand-thumb-down' => 'thumbs-down',
        'hand-thumb-up' => 'thumbs-up',
        'home' => 'house',
        'inbox-stack' => 'tray',
        'light-bulb' => 'lightbulb',
        'lock-closed' => 'lock',
        'magnifying-glass' => 'magnifying-glass',
        'musical-note' => 'music-notes',
        'no-symbol' => 'prohibit',
        'paper-airplane' => 'paper-plane-tilt',
        'photo' => 'image',
        'play-circle' => 'play-circle',
        'scale' => 'scales',
        'shield-exclamation' => 'shield-warning',
        'sparkles' => 'sparkle',
        'squares-2x2' => 'squares-four',
        'user-group' => 'users-three',
        'x-mark' => 'x',
    ];

    /**
     * أيقونة باسمها في Heroicons، بوزن duotone.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function svg(string $name, string $class = '', array $attributes = []): Svg
    {
        return self::make($name, $class, $attributes, false);
    }

    /**
     * المملوءة (heroicon-s) بوزن fill.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function solid(string $name, string $class = '', array $attributes = []): Svg
    {
        return self::make($name, $class, $attributes, true);
    }

    /**
     * اسم الأيقونة في حزمة Phosphor لاسمها في Heroicons.
     */
    public static function phosphor(string $name, bool $solid = false): string
    {
        return 'phosphor-'.(self::MAP[$name] ?? $name).($solid ? '-fill' : '-duotone');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private static function make(string $name, string $class, array $attributes, bool $solid): Svg
    {
        $attributes = ['aria-hidden' => 'true', ...$attributes];
        $factory = app(Factory::class);

        try {
            return $factory->svg(self::phosphor($name, $solid), $class, $attributes);
        } catch (SvgNotFound) {
            return $factory->svg('heroicon-'.($solid ? 's' : 'o').'-'.$name, $class, $attributes);
        }
    }
}
