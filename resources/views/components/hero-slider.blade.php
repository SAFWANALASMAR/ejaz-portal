{{--
    بطل الرئيسية (التصور النهائي — Final-Home): صور الفعاليات في جهة النهاية بعرض 64% تتبدل بتلاشٍ وتكبير بطيء، تحت تدرج
    بالأزرق الداكن صلب في جهة النص. سهمان أبيضان دائريان على الجانبين، ونقاط في الأسفل النشطة كبسولة بشريط يمتلئ بمدة
    الشريحة، وزر إيقاف وتشغيل بجانبها (WCAG 2.2.2). والتبديل التلقائي والحركات تتوقف لمن طلب تقليل الحركة وخارج الشاشة
    (carousel.js وconcept.css). الصورة الأولى وحدها بأولوية التحميل. والمحتوى في الـ slot.

    $slides: list<array{src: string, alt: string}> من HomeShowcase::slides.
--}}
@props(['slides', 'interval' => 6000])

@php($count = count($slides))

<section data-hero-slider data-carousel aria-roledescription="{{ __('ui.slider.carousel') }}" data-slide-label="{{ __('ui.slider.slide') }}"
    data-of-label="{{ __('ui.slider.of') }}" aria-label="{{ __('ui.hero.label') }}"
    @if ($count > 1) data-autoplay data-interval="{{ (int) $interval }}" @endif
    {{ $attributes->class(['concept-hero']) }} data-size="home">
    <div class="concept-hero-media" data-carousel-track aria-live="off">
        @foreach ($slides as $index => $slide)
            <figure data-hero-slide data-carousel-item @if ($index === 0) data-active @endif class="hero-slide m-0" @if ($slide['alt'] === '') aria-hidden="true" @endif>
                <img src="{{ $slide['src'] }}" alt="{{ $slide['alt'] }}" width="1280" height="720"
                    @if ($index === 0) fetchpriority="high" @else loading="lazy" decoding="async" @endif>
            </figure>
        @endforeach
    </div>
    <div class="concept-hero-scrim" aria-hidden="true"></div>

    <div class="relative mx-auto flex min-h-[30rem] max-w-[82.5rem] flex-col justify-end gap-4 px-6 pt-44 pb-14 sm:px-12 lg:h-[29.375rem] lg:min-h-0 lg:justify-center lg:px-24 lg:py-12">
        {{ $slot }}
    </div>

    @if ($count > 1)
        <button type="button" data-carousel-prev class="concept-arrow start-[22px]" aria-label="{{ __('ui.slider.previous') }}">
            {{ \App\Support\Icon::svg('chevron-right', 'size-5', ['aria-hidden' => 'true']) }}
        </button>
        <button type="button" data-carousel-next class="concept-arrow end-[22px]" aria-label="{{ __('ui.slider.next') }}">
            {{ \App\Support\Icon::svg('chevron-left', 'size-5', ['aria-hidden' => 'true']) }}
        </button>

        <div class="absolute inset-x-0 bottom-4 flex items-center justify-center gap-1.5">
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
