<?php

namespace App\Support;

/**
 * أيقونة عنصر القائمة في الرأس وروابط التذييل السريعة (المدير التنفيذي، 31 أكتوبر 01:28): من مسار الرابط، فلا حقل جديد في
 * شاشة القائمة، ويأخذ العنصر الجديد أيقونة قسمه متى كان رابطه في قسم معروف، وإلا أيقونة الرابط العامة.
 */
final class MenuIcon
{
    /**
     * أول جزء من المسار وأيقونته. والرئيسية «/».
     *
     * @var array<string, string>
     */
    public const SECTIONS = [
        '' => 'home',
        'about' => 'building-library',
        'programs' => 'academic-cap',
        'competitions' => 'trophy',
        'initiatives' => 'sparkles',
        'media' => 'newspaper',
        'library' => 'book-open',
        'governance' => 'scale',
        'services' => 'squares-2x2',
        'store' => 'shopping-bag',
        'contact' => 'chat-bubble-left-right',
        'live' => 'video-camera',
        'privacy' => 'shield-check',
        'pages' => 'document-text',
        'assembly' => 'user-group',
        'surveys' => 'face-smile',
    ];

    public const FALLBACK = 'link';

    public static function for(MenuLink $item): string
    {
        $href = $item->href();
        $host = parse_url($href, PHP_URL_HOST);

        // رابط إلى موقع آخر لا يأخذ أيقونة قسم من أقسام البوابة
        if (is_string($host) && $host !== parse_url(url('/'), PHP_URL_HOST)) {
            return self::FALLBACK;
        }

        $path = (string) parse_url($href, PHP_URL_PATH);
        $section = explode('/', trim($path, '/'))[0];

        return self::SECTIONS[$section] ?? self::FALLBACK;
    }
}
