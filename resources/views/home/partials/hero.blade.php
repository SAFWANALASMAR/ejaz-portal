{{--
    البطل (الصورتان 01 و07): الصورة بعرض الصفحة والنص في جهة البداية (اليمين) فوق تدرج فاتح
    يضمن وضوحه على أي صورة. وعلى الجوال: النص أولاً، ثم الصورة تحته.
--}}
<section class="relative flex flex-col bg-surface lg:block">
    <div class="container-page relative z-10 py-10 sm:py-14 lg:flex lg:min-h-[34rem] lg:items-center lg:py-20">
        <div class="max-w-xl">
            <span class="block h-1 w-14 rounded-full bg-accent" aria-hidden="true"></span>
            <h1 class="mt-5 text-3xl leading-tight font-bold text-balance text-primary sm:text-4xl lg:text-5xl lg:leading-tight">{{ $hero['title'] }}</h1>
            <p class="mt-5 text-lg leading-8 text-ink/80">{{ $hero['text'] }}</p>

            @if ($hero['button'])
                <x-button :href="$hero['button']['url']" size="lg" arrow class="mt-8">{{ $hero['button']['label'] }}</x-button>
            @else
                <x-missing :what="__('home.hero.button_missing')" class="mt-8" />
            @endif
        </div>
    </div>

    <div class="relative h-64 sm:h-96 lg:absolute lg:inset-0 lg:h-auto">
        <img src="{{ $hero['image'] }}" alt="{{ $hero['alt'] }}" fetchpriority="high" class="size-full object-cover">
        {{-- لا اتجاه منطقي للتدرج في Tailwind، فيُحدد باتجاه الصفحة: يبدأ من جهة النص (البداية) --}}
        <div class="absolute inset-0 hidden from-surface from-25% via-surface/80 via-45% to-transparent to-70% lg:block ltr:bg-linear-to-r rtl:bg-linear-to-l" aria-hidden="true"></div>
    </div>
</section>
