{{--
    الدوّار المشترك (x-carousel — الإعلانات، وشرائح الرؤية والرسالة والقيم): شريحة ظاهرة واحدة بتلاشٍ ناعم، ونقاط ومؤشر «01 / 03»
    وأسهم. وautoplay يبدّلها تلقائياً بلطف، ويتوقف بالمرور والتركيز وبزر الإيقاف، وخارج الشاشة، ولا يعمل أصلاً لمن طلب تقليل
    الحركة (resources/js/carousel.js). والأسهم ولوحة المفاتيح تعمل دائماً. وبلا سكربت تبقى الشرائح صفاً يُمرَّر أفقياً.
    ARIA على نمط APG: منطقة بـ aria-roledescription للدوّار وشرائحه، وaria-live مهذّب وهو متوقف ومطفأ أثناء التبديل التلقائي.

    كل شريحة في المحتوى عنصر بـ data-carousel-item، والأولى بـ data-active. tone: light أو dark لألوان الأزرار.
    compact: بطاقات التصور الصغيرة (الرؤية والقيم): نقاط صغيرة وزر إيقاف في الزاوية، بلا أسهم ولا مؤشر.
--}}
@props(['label', 'count', 'autoplay' => false, 'interval' => 6000, 'tone' => 'light', 'status' => true, 'compact' => false])

<div data-carousel role="region" aria-roledescription="{{ __('ui.slider.carousel') }}" data-slide-label="{{ __('ui.slider.slide') }}" data-of-label="{{ __('ui.slider.of') }}"
    aria-label="{{ $label }}" data-tone="{{ $tone }}" @if ($compact) data-compact @endif
    @if ($autoplay && $count > 1) data-autoplay data-interval="{{ (int) $interval }}" @endif
    {{ $attributes->class(['carousel']) }}>
    <div data-carousel-track class="carousel-track" aria-live="polite" tabindex="-1">
        {{ $slot }}
    </div>

    @if ($count > 1)
        <div @class(['carousel-controls', 'carousel-controls-compact' => $compact])>
            @unless ($compact)
                {{-- السابق إلى اليمين في الموقع العربي، والتالي إلى اليسار --}}
                <button type="button" data-carousel-prev class="carousel-arrow" aria-label="{{ __('ui.slider.previous') }}">
                    {{ \App\Support\Icon::svg('chevron-right', 'size-5', ['aria-hidden' => 'true']) }}
                </button>
            @endunless
            <div class="flex items-center" data-carousel-dots>
                @for ($index = 0; $index < $count; $index++)
                    <button type="button" data-carousel-dot class="carousel-dot" aria-label="{{ __('ui.slider.go_to', ['number' => $index + 1]) }}"
                        @if ($index === 0) aria-current="true" @endif></button>
                @endfor
            </div>
            @if ($status && ! $compact)
                <span data-carousel-status class="carousel-status tabular-nums" dir="ltr" aria-hidden="true">01 / {{ str_pad((string) $count, 2, '0', STR_PAD_LEFT) }}</span>
            @endif
            @if ($autoplay)
                <button type="button" data-carousel-toggle @class(['carousel-arrow' => ! $compact, 'carousel-pause' => $compact]) aria-pressed="false"
                    data-label-pause="{{ __('ui.slider.pause') }}" data-label-play="{{ __('ui.slider.play') }}" aria-label="{{ __('ui.slider.pause') }}">
                    {{ \App\Support\Icon::solid('pause', $compact ? 'size-3' : 'size-4', ['aria-hidden' => 'true', 'data-icon-pause' => true]) }}
                    {{ \App\Support\Icon::solid('play', $compact ? 'size-3' : 'size-4', ['aria-hidden' => 'true', 'data-icon-play' => true, 'hidden' => true]) }}
                </button>
            @endif
            @unless ($compact)
                <button type="button" data-carousel-next class="carousel-arrow" aria-label="{{ __('ui.slider.next') }}">
                    {{ \App\Support\Icon::svg('chevron-left', 'size-5', ['aria-hidden' => 'true']) }}
                </button>
            @endunless
        </div>
    @endif
</div>
