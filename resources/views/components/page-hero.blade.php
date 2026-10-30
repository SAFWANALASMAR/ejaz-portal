{{--
    رأس صفحات الأقسام (الصورة 05 — النمط الموحد): صورة بعرض الصفحة، والعنوان ووصفه القصير بالأبيض في جهة البداية، ورمز
    الشهب من دليل الهوية في جهة النهاية. والصورة المؤقتة المحايدة حتى تصل صور الجمعية.
    متوازن على كامل العرض (docs/decisions.md — 14 أكتوبر): حافة سفلية مستقيمة، وغطاء موحّد من primary-dark بشفافية 90%
    لا تدرج أفقي، والعنوان الأبيض بتباين 7:1 فأكثر. ومع التحسين البصري (docs/plans/visual-refresh.md): تكبير بطيء للصورة،
    وظهور العنوان كلمة كلمة، ودوائر باهتة، وخط زخرفي بلون التمييز.
--}}
@props(['title', 'summary' => null, 'image' => null])

<section data-page-hero class="section-dark">
    <img src="{{ $image ?? asset('images/placeholders/architecture.svg') }}" alt="" class="ken-burns absolute inset-0 -z-10 size-full object-cover">
    {{-- غطاء موحّد، وتدرج عمودي خفيف من الأسفل يفصل الشريط عما تحته --}}
    <div class="absolute inset-0 -z-10 bg-primary-dark/90" aria-hidden="true"></div>
    <div class="bg-pattern-light absolute inset-0 -z-10" aria-hidden="true"></div>
    <div class="absolute inset-0 -z-10 bg-linear-to-t from-primary-dark/50 to-transparent to-60%" aria-hidden="true"></div>

    {{-- رمز الشهب السبعة من دليل الهوية زخرفةً في جهة النهاية --}}
    <span class="brand-emblem end-6 top-0 hidden h-full w-28 opacity-70 sm:block lg:end-16 lg:w-36" aria-hidden="true"></span>

    <div class="container-page relative py-14 sm:py-20 lg:py-24">
        <h1 data-words class="max-w-3xl text-3xl leading-tight font-bold text-balance text-white sm:text-4xl lg:text-5xl">{{ $title }}</h1>
        <svg class="flourish mt-4 w-32 text-highlight-on-dark" viewBox="0 0 120 12" fill="none" aria-hidden="true">
            <path d="M2 8c18-6 36-6 54 0s38 6 62-2" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
        </svg>
        @if (filled($summary))
            <p class="mt-4 max-w-2xl text-lg leading-8 text-white/85">{{ $summary }}</p>
        @endif
    </div>
</section>
