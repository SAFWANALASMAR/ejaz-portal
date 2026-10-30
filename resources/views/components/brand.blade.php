{{--
    الشعار الكامل بأعمدته، وبجانبه مخطوطة اسم الجمعية بدل الاسم المكتوب (ملاحظات المدير التنفيذي، 25 أكتوبر 2026).
    الشعار من PNG الموقع السابق (جمعية-إعجاز.png بلا السطرين تحته). والمخطوطة مستخرجة من صورة JPG بخلفية سوداء (1280×237)
    في ملفات المدير التنفيذي، بإزالة الخلفية إلى PNG شفاف، ويُنتظر ملفها الأصلي SVG أو PNG شفاف (open-questions.md).
    ونسختاهما البيضاوان للخلفيات الداكنة. وكل ملف منها يُستبدل من «محتوى الرئيسية» (App\Support\HomeContent::brand)، إلا مع
    static: الملفات الأصلية بلا قراءة الإعدادات، لصفحة أخطاء الخادم التي لا تقرأ القاعدة (errors/5xx).
--}}
@props(['light' => false, 'static' => false, 'round' => false])

@php($file = fn (string $key): string => $static ? asset(\App\Support\HomeContent::BRAND[$key]) : \App\Support\HomeContent::brand($key))

{{-- round: الشعار في دائرة زجاجية والمخطوطة أكبر بوضوح (الرأس الداكن — التصور المعتمد، 30 أكتوبر 11:58) --}}
<a href="{{ route('home') }}" wire:navigate @class(['flex shrink-0 items-center', 'gap-2' => ! $round, 'gap-3' => $round])>
    <img src="{{ $file($light ? 'mark_white' : 'mark') }}" alt=""
        width="495" height="505" @class(['w-auto', 'h-11 sm:h-13' => ! $round, 'brand-round size-12 object-contain p-1.5' => $round])>
    <img src="{{ $file($light ? 'calligraphy_white' : 'calligraphy') }}" alt="{{ __('site.name') }}" data-logo-calligraphy
        width="1218" height="185" @class(['w-auto', 'h-5 sm:h-6' => ! $round, 'h-6 max-w-[42vw] object-contain sm:h-8 sm:max-w-none nav:h-7 nav:max-[90rem]:h-6' => $round])>
</a>
