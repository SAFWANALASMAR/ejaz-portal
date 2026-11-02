{{--
    بطل الرئيسية مطابقاً للتصور المعتمد (ملاحظات المدير التنفيذي، 1 نوفمبر 2026، البنود ٧ و٢٦ و٢٧ — Final-Home واللوحة الأولى من ملف
    التصور): خلفية ثابتة بتدرج أزرق وخطوط مائلة رفيعة، لا صورة بعرض البطل، والنص في جهة البداية، وصورة الشريحة في إطار دائري في جهة
    النهاية (وفوق النص على الجوال). والشرائح بالترتيب: تعريف الجمعية، ثم خادم الحرمين الشريفين، ثم رئيس الجمعية، ثم مسابقة البراهين،
    ثم مكتبة إعجاز (App\Support\HomeShowcase::slides). والخلفية واحدة لكل الشرائح، فلا يتبدل إلا النص والإطار.

    التبديل متتابع لا متداخل (البند ٦): تختفي الشريحة الحالية ثم تظهر التالية، فلا يجتمع عنوانان. سهمان على الجانبين، ونقاط في الأسفل
    النشطة كبسولة بشريط يمتلئ بمدة الشريحة، وزر إيقاف وتشغيل بجانبها (WCAG 2.2.2). والشريحة المخفية inert (carousel.js). الصورة الأولى
    وحدها بأولوية التحميل وبنسختي WebP (HeroImage)، والبقية بتحميل كسول. وعنوان الأولى h1 والبقية h2.

    $slides: list<array{key: string, src: string, srcset: ?string, alt: string, title: string, subtitle: ?string, text: ?string, button: ?array{label: string, url: string}, portrait?: bool}>
--}}
@props(['slides', 'interval' => 7000])

@php($count = count($slides))

<section data-hero-slider data-carousel aria-roledescription="{{ __('ui.slider.carousel') }}" data-slide-label="{{ __('ui.slider.slide') }}"
    data-of-label="{{ __('ui.slider.of') }}" aria-label="{{ __('ui.hero.label') }}"
    @if ($count > 1) data-autoplay data-interval="{{ (int) $interval }}" @endif
    {{ $attributes->class(['concept-hero hero-round']) }} data-size="home">
    <span class="hero-round-lines" data-hero-lines aria-hidden="true"></span>

    <div class="concept-hero-media" data-carousel-track aria-live="off" tabindex="-1">
        @foreach ($slides as $index => $slide)
            <figure data-hero-slide="{{ $slide['key'] }}" data-carousel-item @if ($index === 0) data-active @endif class="hero-slide m-0">
                <div class="hero-round-layout mx-auto max-w-[82.5rem] px-6 sm:px-12 lg:px-24">
                    {{-- الإطار الدائري: الصورة تملؤه بلا تمديد فوق مقاسها (الإطار أصغر من أصغر صورة)، وأعلاها ظاهر للصور الطولية --}}
                    <div class="hero-round-frame" data-hero-frame @if ($slide['portrait'] ?? false) data-portrait @endif>
                        <img src="{{ $slide['src'] }}" alt="{{ $slide['alt'] }}" width="640" height="640"
                            @if ($slide['srcset'] ?? null) srcset="{{ $slide['srcset'] }}" sizes="(min-width: 64rem) 22rem, 12rem" @endif
                            @if ($index === 0) fetchpriority="high" @else loading="lazy" decoding="async" @endif>
                    </div>

                    <figcaption class="hero-caption hero-round-text">
                        @if ($index === 0)
                            <h1 class="flex flex-col gap-1.5">
                                <span class="rise-1 text-[2.4rem] leading-[1.08] font-black sm:text-[3.5rem]">{{ $slide['title'] }}</span>
                                @if (filled($slide['subtitle'] ?? null))
                                    <span data-highlight class="rise-2 text-[1.5rem] font-extrabold text-[#DCEBF8] sm:text-[2.25rem]">{{ $slide['subtitle'] }}</span>
                                @endif
                            </h1>
                        @else
                            <h2 class="flex flex-col gap-1.5">
                                <span class="text-[2rem] leading-[1.12] font-black sm:text-[3rem]">{{ $slide['title'] }}</span>
                                @if (filled($slide['subtitle'] ?? null))
                                    <span data-highlight class="text-[1.3rem] font-extrabold text-[#DCEBF8] sm:text-[1.875rem]">{{ $slide['subtitle'] }}</span>
                                @endif
                            </h2>
                        @endif
                        <span class="title-bar" aria-hidden="true"></span>
                        @if (filled($slide['text'] ?? null))
                            <p @class(['max-w-[34rem] text-lg leading-[1.75] text-white/90 sm:text-[1.2rem]', 'rise-3' => $index === 0, 'line-clamp-3 max-sm:line-clamp-2' => $index > 0])>{{ $slide['text'] }}</p>
                        @endif
                        @if ($slide['button'] ?? null)
                            <a href="{{ $slide['button']['url'] }}" @navigate($slide['button']['url']) class="login-pill mt-2 self-start">
                                {{ $slide['button']['label'] }}
                                {{ \App\Support\Icon::svg('arrow-left', 'size-4', ['aria-hidden' => 'true']) }}
                            </a>
                        @endif
                    </figcaption>
                </div>
            </figure>
        @endforeach
    </div>

    {{-- يحجز ارتفاع البطل، والشرائح فوقه بموضع مطلق --}}
    <div class="min-h-[36rem] sm:min-h-[34rem] lg:h-[32rem] lg:min-h-0" aria-hidden="true"></div>

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
