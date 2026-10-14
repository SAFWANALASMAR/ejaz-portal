{{--
    رأس صفحات الأقسام (الصورة 05 — النمط الموحد): صورة بعرض الصفحة، والعنوان ووصفه القصير
    بالأبيض في جهة البداية، ورمز الشهب من دليل الهوية في جهة النهاية. والصورة المؤقتة المحايدة حتى تصل صور الجمعية.
    متوازن على كامل العرض (docs/decisions.md — 14 أكتوبر): حافة سفلية مستقيمة، وغطاء موحّد من primary-dark بشفافية 90%
    لا تدرج أفقي، فلا تثقل جهة على أخرى ولا يظهر حد الطبقة الرمادية في الصورة المؤقتة، والعنوان الأبيض بتباين 7:1 فأكثر.
--}}
@props(['title', 'summary' => null, 'image' => null])

<section data-page-hero class="relative overflow-hidden bg-primary-dark">
    <img src="{{ $image ?? asset('images/placeholders/architecture.svg') }}" alt="" class="absolute inset-0 size-full object-cover">
    {{-- غطاء موحّد، وتدرج عمودي خفيف من الأسفل يفصل الشريط عما تحته --}}
    <div class="absolute inset-0 bg-primary-dark/90" aria-hidden="true"></div>
    <div class="absolute inset-0 bg-linear-to-t from-primary-dark/40 to-transparent to-60%" aria-hidden="true"></div>

    {{-- رمز الشهب السبعة من دليل الهوية زخرفةً في جهة النهاية --}}
    <span class="brand-emblem end-6 top-0 hidden h-full w-28 opacity-70 sm:block lg:end-16 lg:w-36" aria-hidden="true"></span>

    <div class="container-page relative py-14 sm:py-20 lg:py-24">
        <span class="block h-1 w-14 rounded-full bg-accent-light" aria-hidden="true"></span>
        <h1 class="mt-4 max-w-3xl text-3xl leading-tight font-bold text-balance text-white sm:text-4xl lg:text-5xl">{{ $title }}</h1>
        @if (filled($summary))
            <p class="mt-4 max-w-2xl text-lg leading-8 text-white/85">{{ $summary }}</p>
        @endif
    </div>
</section>
