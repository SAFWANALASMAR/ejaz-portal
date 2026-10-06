<?php

namespace App\Filament\Concerns;

use UnitEnum;

/**
 * أسماء المورد ومجموعته في القائمة من ملفات الترجمة (lang/ar/<الوحدة>.php).
 * المورد يحدد: $langKey (مثل 'menu') و$navigationGroupKey (مفتاح في admin.groups).
 */
trait HasTranslatedLabels
{
    public static function getModelLabel(): string
    {
        return __(static::$langKey.'.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __(static::$langKey.'.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __(static::$langKey.'.plural');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.groups.'.static::$navigationGroupKey);
    }
}
