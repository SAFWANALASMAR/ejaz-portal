{{--
    بطل الرئيسية (أمر التصور النهائي، النسخة 63 — البند H1، وFinal-Home): شرائح متبدلة بالترتيب: تعريف الجمعية، ثم خادم الحرمين
    الشريفين، ثم رئيس الجمعية، ثم مسابقة البراهين، ثم مكتبة إعجاز (App\Support\HomeHeroSlides). لكل شريحة صورتها بعرض البطل كله
    (من «محتوى الرئيسية»)، ونصها وزرها فوقها، والتدرج يشتد نحو الأزرق الداكن في الأسفل وفي جهة النص، فلا كتلة لون ثابتة.

    سهمان على الجانبين، ونقاط في الأسفل النشطة كبسولة بشريط يمتلئ بمدة الشريحة، وزر إيقاف وتشغيل بجانبها (WCAG 2.2.2). والتبديل
    التلقائي والحركات تتوقف لمن طلب تقليل الحركة وخارج الشاشة (carousel.js وconcept.css)، والشريحة المخفية inert. الصورة الأولى
    وحدها بأولوية التحميل وبنسختي WebP (srcset 800 و1600 — HeroImage)، والبقية بتحميل كسول. وعنوان الأولى h1 والبقية h2.

    $slides: list<array{key: string, src: string, srcset: ?string, alt: string, title: string, subtitle: ?string, text: ?string, button: ?array{label: string, url: string}}>
--}}
@props(['slides', 'interval' => 7000])

@php($count = count($slides))

<section data-hero-slider data-carousel aria-roledescription="{{ __('ui.slider.carousel') }}" data-slide-label="{{ __('ui.slider.slide') }}"
    data-of-label="{{ __('ui.slider.of') }}" aria-label="{{ __('ui.hero.label') }}"
    @if ($count > 1) data-autoplay data-interval="{{ (int) $interval }}" @endif
    {{ $attributes->class(['concept-hero']) }} data-size="home">
    <div class="concept-hero-media" data-carousel-track aria-live="off" tabindex="-1">
        @foreach ($slides as $index => $slide)
            <figure data-hero-slide="{{ $slide['key'] }}" data-carousel-item @if ($index === 0) data-active @endif class="hero-slide m-0">
                @if ($slide['portrait'] ?? false)
                    {{-- صورة طولية (خادم الحرمين الشريفين، ورئيس الجمعية): في إطار بجانب النص على الحاسوب وفوقه على الجوال، بلا تمديد ولا قص للوجه --}}
                    <div class="hero-portrait-bg" aria-hidden="true"></div>
                @else
                    <img src="{{ $slide['src'] }}" alt="{{ $slide['alt'] }}" width="1280" height="720" data-fit-adaptive @if ($slide['srcset'] ?? null) srcset="{{ $slide['srcset'] }}" sizes="100vw" @endif
                        @if ($index === 0) fetchpriority="high" @else loading="lazy" decoding="async" @endif>
                @endif
                <div class="concept-hero-scrim" aria-hidden="true"></div>

                <figcaption @class(['hero-caption relative mx-auto flex h-full max-w-[82.5rem] flex-col justify-end gap-4 px-6 pb-16 sm:px-12 lg:px-24', 'lg:pe-[30rem]' => $slide['portrait'] ?? false])>
                    @if ($slide['portrait'] ?? false)
                        {{-- الإطار في مجرى النص فوق العنوان على الجوال واللوحي، وفي جهة النهاية بجانبه على الحاسوب (concept.css) --}}
                        <div class="hero-portrait-frame" data-hero-portrait>
                            <img src="{{ $slide['src'] }}" alt="{{ $slide['alt'] }}" width="680" height="850" @if ($index === 0) fetchpriority="high" @else loading="lazy" decoding="async" @endif>
                        </div>
                    @endif
                    @if ($index === 0)
                        <h1 class="flex flex-col gap-1.5">
                            <span class="rise-1 text-[2.6rem] leading-[1.05] font-black sm:text-[4rem]">{{ $slide['title'] }}</span>
                            @if (filled($slide['subtitle'] ?? null))
                                <span data-highlight class="rise-2 text-[1.6rem] font-extrabold text-[#DCEBF8] sm:text-[2.5rem]">{{ $slide['subtitle'] }}</span>
                            @endif
                        </h1>
                    @else
                        <h2 class="flex flex-col gap-1.5">
                            <span class="text-[2.25rem] leading-[1.1] font-black sm:text-[3.25rem]">{{ $slide['title'] }}</span>
                            @if (filled($slide['subtitle'] ?? null))
                                <span data-highlight class="text-[1.4rem] font-extrabold text-[#DCEBF8] sm:text-[2rem]">{{ $slide['subtitle'] }}</span>
                            @endif
                        </h2>
                    @endif
                    <span class="title-bar" aria-hidden="true"></span>
                    @if (filled($slide['text'] ?? null))
                        <p @class(['max-w-[32.5rem] text-lg leading-[1.75] text-white/90 sm:text-[1.25rem]', 'rise-3' => $index === 0, 'line-clamp-3' => $index > 0, 'max-sm:line-clamp-2' => $slide['portrait'] ?? false])>{{ $slide['text'] }}</p>
                    @endif
                    @if ($slide['button'] ?? null)
                        <a href="{{ $slide['button']['url'] }}" @navigate($slide['button']['url']) class="login-pill mt-2 self-start">
                            {{ $slide['button']['label'] }}
                            {{ \App\Support\Icon::svg('arrow-left', 'size-4', ['aria-hidden' => 'true']) }}
                        </a>
                    @endif
                </figcaption>
            </figure>
        @endforeach
    </div>

    {{-- يحجز ارتفاع البطل، والشرائح فوقه بموضع مطلق --}}
    <div class="min-h-[35rem] sm:min-h-[32rem] lg:h-[33rem] lg:min-h-0" aria-hidden="true"></div>

    @if ($count > 1)
        <button type="button" data-carousel-prev class="concept-arrow start-[22px]" aria-label="{{ __('ui.slider.previous') }}">
            {{ \App\Support\Icon::svg('chevron-right', 'size-5', ['aria-hidden' => 'true']) }}
        </button>
        <button type="button" data-carousel-next class="concept-arrow end-[22px]" aria-label="{{ __('ui.slider.next') }}">
            {{ \App\Support\Icon::svg('chevron-left', 'size-5', ['aria-hidden' => 'true']) }}
        </button>

        <div class="absolute inset-x-0 bottom-4 z-[2] flex items-center justify-center gap-1.5">
            <div class="flex items-center" data-hero-progress data-carousel-dots>
                @foreach ($slides as $index => $slide)
                    <button type="button" data-carousel-dot class="concept-dot" aria-label="{{ __('ui.slider.go_to', ['number' => $index + 1]) }}"
                        @if ($index === 0) aria-current="true" @endif><span class="hero-progress-bar"></span></button>
                @endforeach
            </div>
            <button type="button" data-carousel-toggle class="inline-flex size-7 items-center justify-center rounded-full text-white/90 hover:bg-white/15" aria-pressed="false"
                data-label-pause="{{ __('ui.slider.pause') }}" data-label-play="{{ __('ui.slider.play') }}" aria-label="{{ __('ui.slider.pause') }}">
                {{ \App\Support\Icon::solid('pause', 'size-3.5', ['aria-hidden' => 'true', 'data-icon-pause' => true]) }}
                {{ \App\Support\Icon::solid('play', 'size-3.5', ['aria-hidden' => 'true', 'data-icon-play' => true, 'hidden' => true]) }}
            </button>
        </div>
    @endif
</section>
