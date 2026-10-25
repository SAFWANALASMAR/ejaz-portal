{{--
    شريط الأخبار المتحرك (ملاحظات المدير التنفيذي، 25 أكتوبر 2026): عناوين أحدث الأخبار المنشورة، تتحرك ببطء وتتوقف
    بزر الإيقاف (WCAG 2.2.2) وبالمرور عليها والتركيز فيها. والنسخة الثانية لاستمرار الحركة مخفية عن قارئ الشاشة ولوحة
    المفاتيح. ولمن طلب تقليل الحركة: بلا حركة، والشريط يُمرَّر باليد (app.css — .ticker).
--}}
<section class="border-y border-line bg-white" aria-labelledby="home-news-ticker">
    <div class="container-page flex items-center gap-3 py-3 sm:gap-4">
        <h2 id="home-news-ticker" class="flex shrink-0 items-center gap-2 rounded-full bg-accent px-4 py-1.5 text-sm font-bold text-white">
            {{ svg('heroicon-o-newspaper', 'size-4', ['aria-hidden' => 'true']) }}
            {{ __('home.ticker.news') }}
        </h2>

        <div id="home-news-ticker-strip" class="ticker min-w-0 flex-1" data-ticker style="--ticker-duration: {{ max(30, $news->count() * 9) }}s">
            <div class="ticker-track">
                @foreach ([false, true] as $copy)
                    <ul class="ticker-group" @if ($copy) aria-hidden="true" data-ticker-copy @endif>
                        @foreach ($news as $article)
                            <li class="flex shrink-0 items-center gap-3">
                                <span class="size-1.5 rounded-full bg-brand" aria-hidden="true"></span>
                                <a href="{{ route('media.news.show', $article->slug) }}" wire:navigate @if ($copy) tabindex="-1" @endif
                                    class="whitespace-nowrap font-medium text-ink hover:text-primary hover:underline">{{ $article->title }}</a>
                            </li>
                        @endforeach
                    </ul>
                @endforeach
            </div>
        </div>

        <button type="button" data-ticker-toggle="home-news-ticker-strip" aria-pressed="false" data-label-pause="{{ __('home.ticker.pause') }}" data-label-play="{{ __('home.ticker.play') }}"
            aria-label="{{ __('home.ticker.pause') }}" class="inline-flex shrink-0 rounded-full p-2 text-muted hover:bg-surface hover:text-ink">
            {{ svg('heroicon-o-pause', 'size-5', ['aria-hidden' => 'true', 'data-icon-pause' => true]) }}
            {{ svg('heroicon-o-play', 'size-5', ['aria-hidden' => 'true', 'data-icon-play' => true, 'hidden' => true]) }}
        </button>

        <a href="{{ route('media.news.index') }}" wire:navigate class="hidden shrink-0 text-sm font-semibold text-primary hover:text-primary-dark md:inline">{{ __('home.ticker.all_news') }}</a>
    </div>
</section>
