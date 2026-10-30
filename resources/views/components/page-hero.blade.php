{{--
    رأس الصفحات الداخلية (الدفعة الثانية — نطاق صفوان 18:15: «كل صفحة بمستوى الرئيسية»): الصورة بعرض الصفحة تحت تدرج
    شعاعي بألوان الهوية ونقش هندسي يدور ببطء، وشارة صغيرة فوق العنوان بأيقونة، والعنوان يظهر كلمة كلمة بخط منحني يُرسم
    تحته، ثم الوصف. ورمز الشهب من دليل الهوية في جهة النهاية. متوازن على كامل العرض (decisions.md — 14 أكتوبر): حافة
    سفلية مستقيمة، والعنوان الأبيض بتباين 7:1 فأكثر فوق الغطاء. وكل الحركة تتوقف لمن طلب تقليلها وخارج الشاشة.

    الشارة: اسم القسم وأيقونته من اسم المسار (App\Support\PageSections)، وفي صفحة القسم نفسه اسم الجمعية المختصر. ويمرر
    القالب eyebrow وicon ليغيّرهما.
--}}
@props(['title', 'summary' => null, 'image' => null, 'eyebrow' => null, 'icon' => null])

@php
    $section = \App\Support\PageSections::for(request()->route());
    $badge = $eyebrow ?? ($section['label'] === $title ? __('site.short_name') : $section['label']);
@endphp

<section data-page-hero class="page-hero section-dark">
    <img src="{{ $image ?? asset('images/placeholders/architecture.svg') }}" alt="" width="1920" height="640"
        class="ken-burns absolute inset-0 -z-10 size-full object-cover">
    <div class="page-hero-scrim absolute inset-0 -z-10" aria-hidden="true"></div>
    <div class="bg-pattern-light absolute inset-0 -z-10" aria-hidden="true"></div>
    <div class="hero-geometry absolute -z-10" data-loop aria-hidden="true"></div>

    {{-- رمز الشهب السبعة من دليل الهوية زخرفةً في جهة النهاية --}}
    <span class="brand-emblem end-6 top-0 hidden h-full w-28 opacity-60 sm:block lg:end-16 lg:w-36" aria-hidden="true"></span>

    <div class="container-page relative py-12 sm:py-16 lg:py-20">
        <p data-hero-badge class="glass inline-flex items-center gap-2 rounded-full px-4 py-1.5 text-sm text-white">
            {{ \App\Support\Icon::svg($icon ?? $section['icon'], 'size-4 shrink-0 text-brand-light', ['aria-hidden' => 'true']) }}
            {{ $badge }}
        </p>
        <h1 data-words class="mt-5 max-w-3xl text-3xl leading-tight font-black text-balance text-white sm:text-4xl lg:text-5xl">{{ $title }}</h1>
        <svg class="hero-swoosh-static mt-4 h-3 w-40 text-brand-light" viewBox="0 0 420 24" preserveAspectRatio="none" fill="none" aria-hidden="true">
            <path d="M4 16 C 110 2, 300 2, 416 14" stroke="currentColor" stroke-width="4" stroke-linecap="round" />
        </svg>
        @if (filled($summary))
            <p class="mt-4 max-w-2xl text-lg leading-8 text-white/90">{{ $summary }}</p>
        @endif
        {{ $slot ?? '' }}
    </div>
</section>
