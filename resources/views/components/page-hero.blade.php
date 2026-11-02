{{--
    رأس الصفحات الداخلية (التصور النهائي — Final-About وFinal-Page وأخواتهما، ونمط الجوال على كل العروض منذ 31 أكتوبر): بارتفاع
    320 بكسل، الصورة بعرضه كله تحت الرأس مباشرة بتكبير بطيء، والتدرج يشتد نحو الأزرق الداكن في الأسفل وفي جهة النص. وفي أعلاه شريط العودة كبسولتين، ثم شارة القسم «✦ اسم القسم»، ثم
    العنوان، ثم شريط أزرق يمتد، ثم الوصف. والظهور من ضباب كلمة بعد كلمة، وكله يتوقف لمن طلب تقليل الحركة.

    الشارة: اسم القسم من اسم المسار (App\Support\PageSections)، وفي صفحة القسم نفسه اسم الجمعية المختصر. ويمرر القالب eyebrow.
    والصورة المؤقتة المحايدة حتى تصل صور الجمعية. وما بعد الوصف في الـ slot (أزرار أو شارات)، وبطاقة جانبية في
    جهة النهاية في الـ slot المسمى aside (بطاقة جوائز المسابقة)، تنزل تحت النص على الجوال. وcompact: أقصر بمسافات أقل (صفحة
    المسابقة، البند P3). والصورة صورة الصفحة متى مررتها (المرحلة ج)، وإلا الرسم المؤقت.
--}}
@props(['title', 'summary' => null, 'image' => null, 'eyebrow' => null, 'icon' => null, 'compact' => false])

@php
    $section = \App\Support\PageSections::for(request()->route());
    $badge = $eyebrow ?? ($section['label'] === $title ? __('site.short_name') : $section['label']);
    $back = \App\Support\BackNavigation::shows(request()->route());
    // صورة الصفحة المرفوعة من «محتوى الرئيسية» ← «صور رؤوس الصفحات» أولاً، ثم ما يمرره القالب، ثم نقش الصفحة المحايد (NeutralImage)
    $heroSrc = \App\Support\HomeContent::pageHeroUrl(request()->route()?->getName()) ?? $image ?? \App\Support\NeutralImage::forPage(request()->route()?->getName());
@endphp

<section data-page-hero class="concept-hero" data-size="page" @if ($compact) data-compact @endif>
    <div class="concept-hero-media">
        <img src="{{ $heroSrc }}" alt="" width="1280" height="640" data-fit-adaptive>
    </div>
    <div class="concept-hero-scrim" aria-hidden="true"></div>

    <div class="relative mx-auto flex min-h-[20rem] max-w-[82.5rem] flex-col justify-center gap-6 px-4 pt-5.5 pb-11.5 sm:px-7 lg:flex-row lg:items-center lg:justify-between lg:gap-8">
        <div class="flex min-w-0 flex-1 flex-col gap-3">
            @if ($back)
                <x-back-nav inline class="mb-1" />
            @endif
            <p data-hero-badge class="hero-chip rise-1">
                <span aria-hidden="true">✦</span>
                {{ $badge }}
            </p>
            <h1 class="rise-2 max-w-3xl text-[2.25rem] leading-[1.1] font-black text-balance sm:text-[3.25rem]">{{ $title }}</h1>
            <span class="title-bar" aria-hidden="true"></span>
            {{-- مخطوطة اسم الجمعية كبيرة تحت العنوان في كل صفحة داخلية (ملاحظات المدير التنفيذي، 1 نوفمبر 2026، البند ١٤). زخرفة، والاسم في الرأس --}}
            <img src="{{ \App\Support\HomeContent::brand('calligraphy_white') }}" alt="" aria-hidden="true" width="1218" height="185" data-page-calligraphy class="page-hero-calligraphy rise-3">

            @if (filled($summary))
                <p class="rise-3 max-w-[32.5rem] text-lg leading-[1.75] text-white sm:text-[1.1875rem]">{{ $summary }}</p>
            @endif
            {{ $slot ?? '' }}
        </div>

        @isset($aside)
            {{ $aside }}
        @endisset
    </div>
</section>
