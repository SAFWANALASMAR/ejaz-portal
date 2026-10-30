{{--
    بطل الرئيسية (نمط ١، ومرجع المدير التنفيذي — plans/2026-10-30-director-home-reference.md): صور بعرض الشاشة تتبدل بتلاشٍ
    ناعم وتكبير بطيء، فوق تدرج أزرق شعاعي ونقش هندسي باهت وشبكة نقاط تتفاعل مع المؤشر على الحاسوب. شريط تقدم مقسّم (زر لكل
    شريحة)، وأسهم، وزر إيقاف وتشغيل. والتبديل التلقائي والحركات تتوقف لمن طلب تقليل الحركة وخارج الشاشة (carousel.js
    وvisual.css). الصورة الأولى وحدها بأولوية التحميل، والبقية متأخرة. والمحتوى (العنوان والأزرار) في الـ slot.

    $slides: list<array{src: string, alt: string}> من HomeShowcase::slides.
--}}
@props(['slides', 'interval' => 7000])

@php($count = count($slides))

<section data-hero-slider data-carousel aria-roledescription="{{ __('ui.slider.carousel') }}" data-slide-label="{{ __('ui.slider.slide') }}" data-of-label="{{ __('ui.slider.of') }}" aria-label="{{ __('ui.hero.label') }}"
    @if ($count > 1) data-autoplay data-interval="{{ (int) $interval }}" @endif
    {{ $attributes->class(['hero-slider section-dark']) }}>
    <div class="absolute inset-0 -z-10" data-carousel-track aria-live="off">
        @foreach ($slides as $index => $slide)
            <figure data-hero-slide data-carousel-item @if ($index === 0) data-active @endif
                class="hero-slide" @if ($slide['alt'] === '') aria-hidden="true" @endif>
                <img src="{{ $slide['src'] }}" alt="{{ $slide['alt'] }}" width="1920" height="1080"
                    @if ($index === 0) fetchpriority="high" @else loading="lazy" decoding="async" @endif
                    class="hero-image size-full object-cover">
            </figure>
        @endforeach
    </div>

    {{-- الطبقات: تدرج شعاعي بألوان الهوية أعمق في جهة النص، ونقش هندسي يدور ببطء شديد، وشبكة نقاط تتفاعل مع المؤشر --}}
    <div class="hero-scrim absolute inset-0 -z-10" aria-hidden="true"></div>
    <div class="hero-geometry absolute -z-10" data-loop aria-hidden="true"></div>
    <canvas data-hero-grid class="pointer-events-none absolute inset-0 -z-10 size-full" aria-hidden="true"></canvas>

    <div class="container-page relative flex min-h-[inherit] flex-col justify-center pt-14 pb-36 sm:pt-20 lg:pb-40">
        {{ $slot }}
    </div>

    @if ($count > 1)
        <button type="button" data-carousel-prev class="hero-arrow start-3 lg:start-6" aria-label="{{ __('ui.slider.previous') }}">
            {{ \App\Support\Icon::svg('chevron-right', 'size-6', ['aria-hidden' => 'true']) }}
        </button>
        <button type="button" data-carousel-next class="hero-arrow end-3 lg:end-6" aria-label="{{ __('ui.slider.next') }}">
            {{ \App\Support\Icon::svg('chevron-left', 'size-6', ['aria-hidden' => 'true']) }}
        </button>
    @endif

    {{-- الشريط السفلي: اسم الجمعية، والتقدم المقسّم وزر الإيقاف، ومؤشر «مرّر للأسفل» --}}
    <div class="absolute inset-x-0 bottom-0">
        <div class="container-page flex items-center justify-between gap-4 border-t border-white/15 py-4 text-sm text-white/80">
            <span class="hidden truncate sm:block">{{ __('site.name') }}</span>
            @if ($count > 1)
                <div class="flex flex-1 items-center gap-3 sm:max-w-xs">
                    <div class="flex flex-1 gap-1.5" data-hero-progress data-carousel-dots>
                        @foreach ($slides as $index => $slide)
                            <button type="button" data-carousel-dot class="hero-progress" aria-label="{{ __('ui.slider.go_to', ['number' => $index + 1]) }}"
                                @if ($index === 0) aria-current="true" @endif><span class="hero-progress-bar"></span></button>
                        @endforeach
                    </div>
                    <button type="button" data-carousel-toggle class="hero-toggle" aria-pressed="false"
                        data-label-pause="{{ __('ui.slider.pause') }}" data-label-play="{{ __('ui.slider.play') }}" aria-label="{{ __('ui.slider.pause') }}">
                        {{ \App\Support\Icon::svg('pause', 'size-4', ['aria-hidden' => 'true', 'data-icon-pause' => true]) }}
                        {{ \App\Support\Icon::svg('play', 'size-4', ['aria-hidden' => 'true', 'data-icon-play' => true, 'hidden' => true]) }}
                    </button>
                </div>
            @endif
            <a href="#home-identity" class="inline-flex shrink-0 items-center gap-2 rounded-full px-2 py-1 hover:text-white">
                {{ __('ui.hero.scroll') }}
                {{ \App\Support\Icon::svg('arrow-down', 'hero-scroll-cue size-4', ['aria-hidden' => 'true']) }}
            </a>
        </div>
    </div>
</section>
