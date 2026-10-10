{{--
    الشعار الرسمي بالظهور المحلي أفقياً (الأيقونة والاسم) من دليل الهوية، ونسخته البيضاء للخلفيات الداكنة.
    public/images/brand — docs/design-system.md
--}}
@props(['light' => false])

<a href="{{ route('home') }}" class="flex shrink-0 items-center">
    <img
        src="{{ asset($light ? 'images/brand/logo-horizontal-white.svg' : 'images/brand/logo-horizontal.svg') }}"
        alt="{{ __('site.name') }}"
        width="214"
        height="100"
        class="h-12 w-auto sm:h-14"
    >
</a>
