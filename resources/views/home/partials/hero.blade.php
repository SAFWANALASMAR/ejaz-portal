{{--
    بطل الرئيسية (التصور النهائي — Final-Home): العنوان سطران يظهران من ضباب، الأول «جمعية إعجاز» كبيراً والثاني بقية الاسم
    بالأزرق السماوي، ثم شريط أزرق يمتد، ثم التعريف. والنصوص من «إعدادات الموقع» وملف الترجمة (اسم الجمعية واختصاصها)، فلا
    نص من التصور. وزر البطل يظهر متى ضُبط من الإعدادات وحده.
--}}
@php
    $title = $hero['title'];
    $short = __('site.short_name');
    // «جمعية إعجاز» سطراً أول، والبقية («للدراسات والبحوث العلمية») سطراً ثانياً، متى كان العنوان اسم الجمعية
    $rest = str_starts_with($title, $short) ? trim(mb_substr($title, mb_strlen($short))) : '';
@endphp

<x-hero-slider :slides="$slides">
    <h1 class="flex flex-col gap-1.5">
        @if ($rest !== '')
            <span class="rise-1 text-[2.6rem] leading-[1.05] font-black sm:text-[4rem]">{{ $short }}</span>
            <span data-highlight class="rise-2 text-[1.6rem] font-extrabold text-[#DCEBF8] sm:text-[2.5rem]">{{ $rest }}</span>
        @else
            <span data-highlight class="rise-1 text-[2.6rem] leading-[1.05] font-black sm:text-[4rem]">{{ $title }}</span>
        @endif
    </h1>
    <span class="title-bar" aria-hidden="true"></span>
    <p class="rise-3 max-w-[32.5rem] text-lg leading-[1.75] text-white/90 sm:text-[1.375rem]">{{ $hero['text'] }}</p>

    @if ($hero['button'])
        <a href="{{ $hero['button']['url'] }}" @navigate($hero['button']['url']) class="login-pill rise-3 mt-2 self-start">
            {{ $hero['button']['label'] }}
            {{ \App\Support\Icon::svg('arrow-left', 'size-4', ['aria-hidden' => 'true']) }}
        </a>
    @endif
</x-hero-slider>
