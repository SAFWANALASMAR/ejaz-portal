{{--
    شريحة في دوّار «إعلانات» بالرئيسية (ملاحظة صفوان ٣٠، 3 نوفمبر 2026): الصورة (أو نقش الهوية بلا صورة) في إطار بزوايا مستديرة،
    ثم الشارة والعنوان وسطران من الوصف، وصف البيانات (التاريخ بأيقونة، وكبسولة الجوائز لمسابقة البراهين)، ثم الزر. والنقش الزخرفي
    باهت خلف المحتوى. والصورة جهة اليسار على الحاسوب واللوحي، وفوق النص على الجوال. وما فرغ لا سطر له.

    $slide من HomeShowcase::feed: image (رابط)، cover (نقش الهوية لا صورة)، badge، title، text، date (Carbon)، date_label، with_time،
    total (مجموع الجوائز أو null)، href، external، cta.
--}}
<article data-announcement-slide class="announcement-feature feed-slide">
    <svg class="feed-slide-pattern" viewBox="0 0 200 200" fill="none" stroke="currentColor" aria-hidden="true">
        <path d="M100 10 L190 100 L100 190 L10 100 Z" /><path d="M100 40 L160 100 L100 160 L40 100 Z" /><circle cx="100" cy="100" r="40" />
    </svg>

    <span data-slide-media @class(['feed-slide-media', 'feed-slide-media-cover' => $slide['cover']])>
        <img src="{{ $slide['image'] }}" alt="" loading="lazy" width="480" height="360" class="size-full object-cover">
        @if ($slide['cover'])
            {{-- غلاف بهوية الجمعية حين لا صورة: نقشها ورمز شعارها الأبيض --}}
            <img src="{{ asset('images/brand/logo-mark-white.png') }}" alt="" loading="lazy" width="495" height="505" class="feed-slide-mark">
        @endif
    </span>

    <div data-slide-body class="feed-slide-body">
        @if (filled($slide['badge']))
            <span data-slide-badge class="feed-slide-badge">{{ $slide['badge'] }}</span>
        @endif

        <h3 data-slide-title class="feed-slide-title">{{ $slide['title'] }}</h3>

        @if (filled($slide['text']))
            <p data-slide-text class="feed-slide-text">{{ $slide['text'] }}</p>
        @endif

        @if ($slide['date'] !== null || $slide['total'] !== null)
            <div class="feed-slide-meta">
                @if ($slide['date'] !== null)
                    <span data-slide-date class="feed-slide-date">
                        {{ \App\Support\Icon::svg('calendar-days', 'size-5 shrink-0', ['aria-hidden' => 'true']) }}
                        <span>
                            <span class="feed-slide-date-label">{{ $slide['date_label'] }}</span>
                            {{-- بتوقيت الرياض كالنص المعروض، لا يوم UTC --}}
                            <time datetime="{{ $slide['with_time'] ? \App\Support\Dates::local($slide['date'])->toIso8601String() : \App\Support\Dates::local($slide['date'])->toDateString() }}">{{ \App\Support\Dates::display($slide['date'], withTime: $slide['with_time']) }}</time>
                        </span>
                    </span>
                @endif

                @if ($slide['total'] !== null)
                    <span data-prize-total class="feed-slide-prize">
                        {{ \App\Support\Icon::svg('trophy', 'size-5 shrink-0', ['aria-hidden' => 'true']) }}
                        <span class="flex flex-col leading-tight">
                            <span class="text-xs">{{ __('ui.prize.title') }}</span>
                            <span>
                                {{-- العدّ داخل منطقة الدوّار الحية كان يُقرأ رقماً رقماً (فحص الوصول 0.15): الظاهر مخفي عن قارئ الشاشة، والنهائي له ثابت --}}
                                <span class="text-lg font-black tabular-nums" aria-hidden="true" data-count-to="{{ $slide['total'] }}">{{ number_format($slide['total']) }}</span>
                                <span class="sr-only">{{ number_format($slide['total']) }}</span>
                                <span class="text-xs">{{ __('ui.prize.currency') }}</span>
                            </span>
                        </span>
                    </span>
                @endif
            </div>
        @endif

        @if ($slide['href'] !== null)
            <a href="{{ $slide['href'] }}" class="feed-slide-cta"
                @if ($slide['external']) target="_blank" rel="noopener noreferrer" @else @navigate($slide['href']) @endif>
                {{ $slide['cta'] }}<span class="sr-only">: {{ $slide['title'] }}</span>
                @if ($slide['external'])
                    <span class="sr-only">({{ __('site.opens_in_new_tab') }})</span>
                @endif
                {{ \App\Support\Icon::svg($slide['external'] ? 'arrow-up-left' : 'arrow-left', 'size-4', ['aria-hidden' => 'true']) }}
            </a>
        @endif
    </div>
</article>
