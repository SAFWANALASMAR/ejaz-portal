{{--
    الشعار الكامل بأعمدته، وبجانبه مخطوطة اسم الجمعية بدل الاسم المكتوب (ملاحظات المدير التنفيذي، 25 أكتوبر 2026):
    الشعار من ملفات الموقع السابق (جمعية-إعجاز.png بلا السطرين تحته)، والمخطوطة من ملف المدير التنفيذي بخلفية شفافة
    (inputs/2026-10-10-executive-director). ونسختاهما البيضاوان للخلفيات الداكنة. public/images/brand — docs/design-system.md
--}}
@props(['light' => false])

<a href="{{ route('home') }}" wire:navigate class="flex shrink-0 items-center gap-2">
    <img src="{{ asset($light ? 'images/brand/logo-mark-white.png' : 'images/brand/logo-mark.png') }}" alt=""
        width="495" height="505" class="h-11 w-auto sm:h-13">
    <img src="{{ asset($light ? 'images/brand/logo-calligraphy-white.png' : 'images/brand/logo-calligraphy.png') }}" alt="{{ __('site.name') }}"
        width="1218" height="185" class="h-5 w-auto sm:h-6">
</a>
