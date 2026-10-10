{{--
    رأس صفحات الأقسام (الصورة 05 — النمط الموحد): صورة بعرض الصفحة، والعنوان ووصفه القصير
    بالأبيض فوق تدرج داكن من جهة البداية، ورمز الشهب من دليل الهوية، وحافة سفلية مائلة. والصورة المؤقتة المحايدة حتى تصل صور الجمعية.
--}}
@props(['title', 'summary' => null, 'image' => null])

<section class="relative overflow-hidden bg-primary-dark [clip-path:polygon(0_0,100%_0,100%_100%,0_calc(100%-1.75rem))]">
    <img src="{{ $image ?? asset('images/placeholders/architecture.svg') }}" alt="" class="absolute inset-0 size-full object-cover">
    {{-- لا اتجاه منطقي للتدرج في Tailwind، فيُحدد باتجاه الصفحة: يبدأ داكناً من جهة النص (البداية) --}}
    <div class="absolute inset-0 from-primary-dark from-20% via-primary-dark/85 via-50% to-primary-dark/30 ltr:bg-linear-to-r rtl:bg-linear-to-l" aria-hidden="true"></div>

    {{-- رمز الشهب السبعة من دليل الهوية زخرفةً في جهة النهاية --}}
    <span class="brand-emblem end-6 top-0 hidden h-full w-28 opacity-70 sm:block lg:end-16 lg:w-36" aria-hidden="true"></span>

    <div class="container-page relative py-14 pb-20 sm:py-20 sm:pb-24 lg:py-24 lg:pb-28">
        <span class="block h-1 w-14 rounded-full bg-accent-light" aria-hidden="true"></span>
        <h1 class="mt-4 max-w-3xl text-3xl leading-tight font-bold text-balance text-white sm:text-4xl lg:text-5xl">{{ $title }}</h1>
        @if (filled($summary))
            <p class="mt-4 max-w-2xl text-lg leading-8 text-white/85">{{ $summary }}</p>
        @endif
    </div>
</section>
