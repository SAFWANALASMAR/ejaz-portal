{{--
    «فاتح بصورة واحدة» (إضافة توجيه المدير التنفيذي، 3 نوفمبر 2026، البندان ٢٢ و٢٣ — docs/decisions/2026-11-03-director-home.md، ويحل
    محل الإطار الدائري وما يلي منه): خلفية بتدرج أزرق فاتح تختلف درجته بين الشرائح، ونص داكن، وصورة واحدة في جهة النهاية (اليسار)
    بإطار أبيض 8 بكسل مائلة −4° وخلفها بطاقة مائلة عكسها، وفوق النص على الجوال بلا البطاقة الخلفية. والشرائح أربع بالترتيب: المصحف
    الشريف، والحرم المكي، وخادم الحرمين الشريفين بحلقة ذهبية وخضراء، ورئيس مجلس الإدارة. وما لا صورة معتمدة له مربع محايد بعلامة
    «【ناقص】». والتبديل كل 6 ثوانٍ، ويتوقف لمن طلب تقليل الحركة (carousel.js).

    وكان: بطل الرئيسية مطابقاً للتصور المعتمد (ملاحظات المدير التنفيذي، 1 نوفمبر 2026، البنود ٧ و٢٦ و٢٧ — Final-Home واللوحة الأولى من ملف
    التصور): خلفية ثابتة بتدرج أزرق وخطوط مائلة رفيعة، لا صورة بعرض البطل، والنص في جهة البداية، وصورة الشريحة في إطار دائري في جهة
    النهاية (وفوق النص على الجوال). والشرائح بالترتيب: تعريف الجمعية، ثم خادم الحرمين الشريفين، ثم رئيس الجمعية، ثم مسابقة البراهين،
    ثم مكتبة إعجاز (App\Support\HomeShowcase::slides). والخلفية واحدة لكل الشرائح، فلا يتبدل إلا النص والإطار.

    التبديل متتابع لا متداخل (البند ٦): تختفي الشريحة الحالية ثم تظهر التالية، فلا يجتمع عنوانان. سهمان على الجانبين، ونقاط في الأسفل
    النشطة كبسولة بشريط يمتلئ بمدة الشريحة، وزر إيقاف وتشغيل بجانبها (WCAG 2.2.2). والشريحة المخفية inert (carousel.js). الصورة الأولى
    وحدها بأولوية التحميل وبنسختي WebP (HeroImage)، والبقية بتحميل كسول. وعنوان الأولى h1 والبقية h2.

    $slides: list<array{key: string, src: string, srcset: ?string, alt: string, title: string, subtitle: ?string, text: ?string, buttons: list<array{label: string, url: string, secondary: bool}>, badges: list<string>, missing: bool}>
--}}
@props(['slides', 'interval' => 6000])

@php($count = count($slides))

<section data-hero-slider data-carousel aria-roledescription="{{ __('ui.slider.carousel') }}" data-slide-label="{{ __('ui.slider.slide') }}"
    data-of-label="{{ __('ui.slider.of') }}" aria-label="{{ __('ui.hero.label') }}"
    @if ($count > 1) data-autoplay data-interval="{{ (int) $interval }}" @endif
    {{ $attributes->class(['concept-hero hero-round hero-light']) }} data-size="home">
    <span class="hero-round-lines" data-hero-lines aria-hidden="true"></span>

    <div class="concept-hero-media" data-carousel-track aria-live="off" tabindex="-1">
        @foreach ($slides as $index => $slide)
            <figure data-hero-slide="{{ $slide['key'] }}" data-carousel-item @if ($index === 0) data-active @endif class="hero-slide m-0">
                <div class="hero-round-layout mx-auto max-w-[82.5rem] px-6 sm:px-12 lg:px-24">
                    {{-- صورة واحدة بإطار أبيض مائل، وخلفها بطاقة مائلة عكسها (حاسوب)، وما لا صورة معتمدة له مربع محايد بعلامة «【ناقص】» --}}
                    <div class="hero-light-visual">
                        <span class="hero-light-back" aria-hidden="true"></span>
                        <div class="hero-light-frame" data-hero-frame @if ($slide['missing'] ?? false) data-missing @endif>
                            <img src="{{ $slide['src'] }}" alt="{{ $slide['alt'] }}" width="640" height="586"
                                @if ($slide['srcset'] ?? null) srcset="{{ $slide['srcset'] }}" sizes="(min-width: 64rem) 22.5rem, 90vw" @endif
                                @if ($index === 0) fetchpriority="high" @else loading="lazy" decoding="async" @endif>
                            @if ($slide['missing'] ?? false)
                                <x-missing :what="__('ui.hero.image_missing')" class="hero-light-missing" data-hero-image-missing />
                            @endif
                        </div>
                    </div>

                    <figcaption class="hero-caption hero-round-text">
                        {{-- شارتا الشريحة الأولى من حقائق docs/overview.md وحدها (تقييم لقطات التوجيه، البند ٦) --}}
                        @if (($slide['badges'] ?? []) !== [])
                            <span class="hero-light-badges" data-hero-badges>
                                @foreach ($slide['badges'] as $badge)
                                    <span class="hero-chip">{{ $badge }}</span>
                                @endforeach
                            </span>
                        @endif
                        @if ($index === 0)
                            <h1 class="flex flex-col gap-1.5">
                                <span class="rise-1 text-[2rem] leading-[1.08] font-black sm:text-[3.5rem]">{{ $slide['title'] }}</span>
                                @if (filled($slide['subtitle'] ?? null))
                                    <span data-highlight class="rise-2 text-[1.125rem] font-extrabold text-[#DCEBF8] sm:text-[2.25rem]">{{ $slide['subtitle'] }}</span>
                                @endif
                            </h1>
                        @else
                            {{-- عنوان شريحة رئيس المجلس أصغر وصفته سطر واحد على الحاسوب (البند ٩ — hero-light-title في concept.css) --}}
                            <h2 class="flex flex-col gap-1.5">
                                <span class="hero-light-title text-[1.75rem] leading-[1.12] font-black sm:text-[3rem]">{{ $slide['title'] }}</span>
                                @if (filled($slide['subtitle'] ?? null))
                                    <span data-highlight class="hero-light-subtitle text-[1.125rem] font-extrabold text-[#DCEBF8] sm:text-[1.875rem]">{{ $slide['subtitle'] }}</span>
                                @endif
                            </h2>
                        @endif
                        <span class="title-bar" aria-hidden="true"></span>
                        {{-- اقتباس خادم الحرمين كاملاً بلا قص (البند ٧)، وما سواه في الشرائح التالية بثلاثة أسطر --}}
                        @if (filled($slide['text'] ?? null))
                            <p data-hero-text @class(['hero-light-text max-w-[34rem] text-base leading-[1.75] text-white sm:text-[1.2rem]', 'rise-3' => $index === 0, 'line-clamp-3 max-sm:line-clamp-2' => $index > 0 && $slide['key'] !== 'king'])>{{ $slide['text'] }}</p>
                        @endif
                        {{-- الزر الرئيسي داكن والثانوي بإطار (البند ٢٣، وتقييم اللقطات، البند ٦) --}}
                        @if (($slide['buttons'] ?? []) !== [])
                            <span class="mt-2 flex flex-wrap gap-3" data-hero-buttons>
                                @foreach ($slide['buttons'] as $button)
                                    <a href="{{ $button['url'] }}" @navigate($button['url']) @class(['login-pill', 'hero-light-secondary' => $button['secondary']]) data-hero-button="{{ $button['secondary'] ? 'secondary' : 'primary' }}">
                                        {{ $button['label'] }}
                                        @unless ($button['secondary'])
                                            {{ \App\Support\Icon::svg('arrow-left', 'size-4', ['aria-hidden' => 'true']) }}
                                        @endunless
                                    </a>
                                @endforeach
                            </span>
                        @endif
                    </figcaption>
                </div>
            </figure>
        @endforeach
    </div>

    {{-- يحجز ارتفاع البطل من 768 والشرائح فوقه بموضع مطلق. وعلى الجوال لا يحجز شيئاً: الشرائح في خانة شبكة واحدة فارتفاع البطل ارتفاع أطولها (concept.css) --}}
    <div class="md:min-h-[34rem] lg:h-[32rem] lg:min-h-0" aria-hidden="true"></div>

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
            <button type="button" data-carousel-toggle class="inline-flex size-7 items-center justify-center rounded-full text-white hover:bg-white/15" aria-pressed="false"
                data-label-pause="{{ __('ui.slider.pause') }}" data-label-play="{{ __('ui.slider.play') }}" aria-label="{{ __('ui.slider.pause') }}">
                {{ \App\Support\Icon::solid('pause', 'size-3.5', ['aria-hidden' => 'true', 'data-icon-pause' => true]) }}
                {{ \App\Support\Icon::solid('play', 'size-3.5', ['aria-hidden' => 'true', 'data-icon-play' => true, 'hidden' => true]) }}
            </button>
        </div>
    @endif
</section>
