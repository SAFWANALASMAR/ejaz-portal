{{--
    الشعار الرسمي بأعمدته، وبجانبه مخطوطة اسم الجمعية بدل الاسم المكتوب (ملاحظات المدير التنفيذي، 25 أكتوبر 2026، والتصور النهائي:
    الشعار 54 بكسل والمخطوطة 40 في الرأس، و52 و35 في التذييل). من ملفات الهوية المعتمدة بلا تعديل (design-system.md).
    وكل ملف منها يُستبدل من «محتوى الرئيسية» (App\Support\HomeContent::brand)، إلا مع static: الملفات الأصلية بلا قراءة
    الإعدادات، لصفحة أخطاء الخادم التي لا تقرأ القاعدة (errors/5xx).
    light: النسختان البيضاوان للخلفيات الداكنة. link: رابط إلى الرئيسية (false داخل رابط آخر). size: header أو footer أو masthead.
--}}
@props(['light' => false, 'static' => false, 'link' => true, 'size' => 'header'])

@php
    $file = fn (string $key): string => $static ? asset(\App\Support\HomeContent::BRAND[$key]) : \App\Support\HomeContent::brand($key);
    // masthead: الصف الأول من الرأس (أمر التصور النهائي، النسخة 63 — البند R2): الرمز كبيراً والمخطوطة كبيرة ممتدة (98 و90
    // بكسل على الحاسوب العريض)، وتصغر قليلاً عند التمرير (concept.css)
    [$mark, $calligraphy] = match ($size) {
        'footer' => ['h-[3.25rem]', 'h-[2.2rem]'],
        // ومقاساتهما في concept.css (.masthead-mark و.masthead-calligraphy) لأن الرأس كله يصغر بتناسب عند التمرير (البند ٢٥)
        'masthead' => ['masthead-mark', 'masthead-calligraphy'],
        default => ['h-11 sm:h-[3.375rem]', 'h-7 max-w-[44vw] sm:h-10 sm:max-w-none'],
    };
@endphp

@if ($link)
    <a href="{{ route('home') }}" wire:navigate {{ $attributes->class(['flex shrink-0 items-center gap-3']) }}>
@else
    <span {{ $attributes->class(['flex shrink-0 items-center gap-3']) }}>
@endif
    <img src="{{ $file($light ? 'mark_white' : 'mark') }}" alt="" width="495" height="505" data-brand-mark class="{{ $mark }} w-auto shrink-0">
    <img src="{{ $file($light ? 'calligraphy_white' : 'calligraphy') }}" alt="{{ __('site.name') }}" data-logo-calligraphy
        width="1218" height="185" class="{{ $calligraphy }} w-auto object-contain">
@if ($link)
    </a>
@else
    </span>
@endif
