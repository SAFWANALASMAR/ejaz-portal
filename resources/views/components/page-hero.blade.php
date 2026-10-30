{{--
    رأس الصفحات الداخلية (التصور النهائي — Final-About وFinal-Page وأخواتهما): بارتفاع 320 بكسل، الصورة في جهة النهاية بعرض 60%
    بتكبير بطيء تحت تدرج بالأزرق الداكن صلب في جهة النص. وفي أعلاه شريط العودة كبسولتين، ثم شارة القسم «✦ اسم القسم»، ثم
    العنوان، ثم شريط أزرق يمتد، ثم الوصف. والظهور من ضباب كلمة بعد كلمة، وكله يتوقف لمن طلب تقليل الحركة.

    الشارة: اسم القسم من اسم المسار (App\Support\PageSections)، وفي صفحة القسم نفسه اسم الجمعية المختصر. ويمرر القالب eyebrow.
    والصورة المؤقتة المحايدة حتى تصل صور الجمعية. وما بعد الوصف في الـ slot (أزرار أو شارات).
--}}
@props(['title', 'summary' => null, 'image' => null, 'eyebrow' => null, 'icon' => null])

@php
    $section = \App\Support\PageSections::for(request()->route());
    $badge = $eyebrow ?? ($section['label'] === $title ? __('site.short_name') : $section['label']);
    $back = \App\Support\BackNavigation::shows(request()->route());
@endphp

<section data-page-hero class="concept-hero" data-size="page">
    <div class="concept-hero-media">
        <img src="{{ $image ?? asset('images/placeholders/architecture.svg') }}" alt="" width="1280" height="640">
    </div>
    <div class="concept-hero-scrim" aria-hidden="true"></div>

    <div class="relative mx-auto flex min-h-[20rem] max-w-[82.5rem] flex-col justify-center gap-3 px-4 pt-5.5 pb-11.5 sm:px-7">
        @if ($back)
            <x-back-nav inline class="mb-1" />
        @endif
        <p data-hero-badge class="hero-chip rise-1">
            <span aria-hidden="true">✦</span>
            {{ $badge }}
        </p>
        <h1 class="rise-2 max-w-3xl text-[2.25rem] leading-[1.1] font-black text-balance sm:text-[3.25rem]">{{ $title }}</h1>
        <span class="title-bar" aria-hidden="true"></span>
        @if (filled($summary))
            <p class="rise-3 max-w-[32.5rem] text-lg leading-[1.75] text-white/90 sm:text-[1.1875rem]">{{ $summary }}</p>
        @endif
        {{ $slot ?? '' }}
    </div>
</section>
