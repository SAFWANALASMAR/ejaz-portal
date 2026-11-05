<?php

namespace App\Support;

use Illuminate\Routing\Route;

/**
 * القسم الذي تنتمي إليه الصفحة الداخلية، لشارة رأسها (x-page-hero) وأيقونتها: من بادئة اسم المسار في جدول واحد، فلا يمرره
 * كل قالب. والاسم من ترجمة القسم نفسه، فلا نص جديد. وما ليس في الجدول شارته اسم الجمعية المختصر.
 */
final class PageSections
{
    /**
     * بادئة اسم المسار ← [مفتاح ترجمة اسم القسم، والأيقونة].
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const SECTIONS = [
        'governance.' => ['governance.title', 'shield-check'],
        'library.' => ['library.title', 'book-open'],
        'media.' => ['media.title', 'megaphone'],
        'programs.' => ['programs.title', 'academic-cap'],
        'competitions.' => ['competitions.list.heading', 'trophy'],
        'services.' => ['services.title', 'squares-2x2'],
        'initiatives.' => ['content.initiatives.title', 'light-bulb'],
        'assembly.' => ['assembly.title', 'user-group'],
        'contact' => ['contact.title', 'chat-bubble-left-right'],
        'live' => ['livestream.title', 'video-camera'],
        'store.' => ['store.page.title', 'storefront'],
    ];

    /**
     * @return array{label: string, icon: string}
     */
    public static function for(?Route $route): array
    {
        $name = (string) $route?->getName();

        foreach (self::SECTIONS as $prefix => [$label, $icon]) {
            if ($name === rtrim($prefix, '.') || str_starts_with($name, $prefix)) {
                return ['label' => (string) __($label), 'icon' => $icon];
            }
        }

        return ['label' => (string) __('site.short_name'), 'icon' => 'sparkles'];
    }
}
