{{--
    الشعار الكامل بأعمدته، وبجانبه مخطوطة اسم الجمعية بدل الاسم المكتوب (ملاحظات المدير التنفيذي، 25 أكتوبر 2026).
    الشعار من PNG الموقع السابق (جمعية-إعجاز.png بلا السطرين تحته). والمخطوطة مستخرجة من صورة JPG بخلفية سوداء (1280×237)
    في ملفات المدير التنفيذي، بإزالة الخلفية إلى PNG شفاف، ويُنتظر ملفها الأصلي SVG أو PNG شفاف (open-questions.md).
    ونسختاهما البيضاوان للخلفيات الداكنة. وكل ملف منها يُستبدل من «محتوى الرئيسية» (App\Support\HomeContent::brand)، إلا مع
    static: الملفات الأصلية بلا قراءة الإعدادات، لصفحة أخطاء الخادم التي لا تقرأ القاعدة (errors/5xx).
--}}
@props(['light' => false, 'static' => false])

@php($file = fn (string $key): string => $static ? asset(\App\Support\HomeContent::BRAND[$key]) : \App\Support\HomeContent::brand($key))

<a href="{{ route('home') }}" wire:navigate class="flex shrink-0 items-center gap-2">
    <img src="{{ $file($light ? 'mark_white' : 'mark') }}" alt=""
        width="495" height="505" class="h-11 w-auto sm:h-13">
    <img src="{{ $file($light ? 'calligraphy_white' : 'calligraphy') }}" alt="{{ __('site.name') }}"
        width="1218" height="185" class="h-5 w-auto sm:h-6">
</a>
