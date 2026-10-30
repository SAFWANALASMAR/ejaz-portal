{{--
    البطل (الصورتان 01 و07، والمرجع الأسلوبي — docs/plans/visual-refresh.md): الصورة بعرض الصفحة كاملة تحت تدرج داكن من
    ألوان الهوية، والنص الأبيض الكبير في جهة البداية (اليمين). تكبير بطيء للصورة، وظهور العنوان كلمة كلمة، وزر كبسولي
    بلمعة، وكلها تتوقف لمن طلب تقليل الحركة. والتدرج أعمق في جهة النص، فيبقى تباينه مقبولاً على أي صورة.
--}}
<section class="section-dark">
    <img src="{{ $hero['image'] }}" alt="{{ $hero['alt'] }}" fetchpriority="high" class="ken-burns absolute inset-0 -z-10 size-full object-cover">
    {{-- لا اتجاه منطقي للتدرج في Tailwind، فيُحدد باتجاه الصفحة: أعمق في جهة النص (البداية) --}}
    <div class="absolute inset-0 -z-10 from-primary-dark from-30% via-primary-dark/85 via-55% to-primary-dark/45 ltr:bg-linear-to-r rtl:bg-linear-to-l" aria-hidden="true"></div>
    <div class="bg-pattern-light absolute inset-0 -z-10 opacity-70" aria-hidden="true"></div>

    <div class="container-page relative flex min-h-[28rem] items-center py-16 sm:min-h-[32rem] lg:min-h-[38rem] lg:py-24">
        <div class="max-w-2xl">
            <h1 data-words class="text-4xl leading-tight font-bold text-balance text-white sm:text-5xl lg:text-6xl lg:leading-tight">{{ $hero['title'] }}</h1>
            <svg class="flourish mt-5 w-36 text-highlight-on-dark" viewBox="0 0 120 12" fill="none" aria-hidden="true">
                <path d="M2 8c18-6 36-6 54 0s38 6 62-2" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
            </svg>
            <p class="mt-5 max-w-xl text-lg leading-9 text-white/85 sm:text-xl">{{ $hero['text'] }}</p>

            @if ($hero['button'])
                <x-button :href="$hero['button']['url']" variant="accent" size="lg" arrow class="mt-9 ring-1 ring-white/20">{{ $hero['button']['label'] }}</x-button>
            @else
                <x-missing :what="__('home.hero.button_missing')" class="mt-9" />
            @endif
        </div>
    </div>
</section>
