{{--
    «إعلانات» و«أخبار الجمعية» جنباً إلى جنب على الحاسوب، ومتراصّتان على اللوحي والجوال (أمر التصور النهائي، النسخة 63 — البنود R7
    وH7 وH8؛ وملاحظة صفوان ٣٠، 3 نوفمبر 2026). في الأولى دوّار شرائحه بارتفاع أطولها (home/partials/feed-slide): مسابقة البراهين أولاً
    بحالتها وموعدها ومجموع جوائزها من جدولها يعدّ تصاعدياً حين يظهر (data-count-to — motion.js، والرقم كاملاً بلا سكربت)، ثم إعلانات
    اللوحة. وفي الثانية شريط يصعد بلا انقطاع (x-marquee عمودي) بارتفاع العمود المجاور، كل خبر بصورته المصغرة وعنوانه بسطرين وتاريخه
    وتصنيفه، ويتوقف بالمرور والتركيز وزر الإيقاف ولمن طلب تقليل الحركة، وتحته «كل الأخبار». ولكل منهما «المزيد». والعمود الفارغ
    باقٍ بحالته الفارغة، فالعمودان متجاوران دائماً على الحاسوب ما دام إعدادا إظهارهما مفعّلين (قرار صفوان، 11 نوفمبر 2026 —
    docs/decisions/2026-11-11-fix-show-empty-sections.md). والمحتوى من اللوحة وحدها، والعنوانان من «محتوى الرئيسية» (tickers).
    $showsFeed و$showsNews: إعدادا الإظهار (والبراهين تُظهر عمود الإعلانات).
--}}
@php
    $feed = \App\Support\HomeShowcase::feed($prize, $announcements);
    $latest = $news->take(6);
@endphp

<section class="home-band" data-band="{{ $band }}" aria-label="{{ $tickers['announcements'] }} — {{ $tickers['news'] }}">
    <div @class(['mx-auto grid max-w-[82.5rem] gap-6 px-4 pt-6 pb-7 sm:px-7 lg:gap-8 lg:pt-7 lg:pb-8', 'lg:grid-cols-2' => $showsFeed && $showsNews])>
        @if ($showsFeed)
            <div data-feed-column class="flex min-w-0 flex-col gap-3" data-reveal>
                <div class="flex items-center justify-between gap-4">
                    <h2 id="home-announcements" class="section-heading-title flex items-center gap-2.5 font-black text-primary-dark">
                        <x-icon-tile icon="bell" size="sm" shade="medium" class="section-heading-icon" />
                        {{ $tickers['announcements'] }}
                    </h2>
                    <a href="{{ route('media.announcements') }}" wire:navigate class="more-link">{{ __('ui.announcements.more') }}<span class="sr-only">: {{ $tickers['announcements'] }}</span></a>
                </div>

                @if ($feed === [])
                    <x-empty-state icon="bell" :message="__('home.empty.announcements')" class="flex-1" data-home-empty="announcements" />
                @else
                    <x-carousel :label="$tickers['announcements']" :count="count($feed)" autoplay :interval="7000" :status="false" class="home-announcements feed-carousel">
                        @foreach ($feed as $slide)
                            <div data-carousel-item @if ($loop->first) data-active @endif>
                                @include('home.partials.feed-slide', ['slide' => $slide])
                            </div>
                        @endforeach
                    </x-carousel>
                @endif
            </div>
        @endif

        @if ($showsNews)
            <div data-feed-column class="flex min-w-0 flex-col gap-3" data-reveal style="--reveal-delay: 120ms">
                <div class="flex items-center justify-between gap-4">
                    <h2 id="home-news" class="section-heading-title flex items-center gap-2.5 font-black text-primary-dark">
                        <x-icon-tile icon="megaphone" size="sm" shade="medium" class="section-heading-icon" />
                        {{ $tickers['news'] }}
                    </h2>
                    <a href="{{ route('media.news.index') }}" wire:navigate class="more-link">{{ __('ui.news.more') }}<span class="sr-only">: {{ $tickers['news'] }}</span></a>
                </div>

                @if ($latest->isEmpty())
                    <x-empty-state icon="newspaper" :message="__('home.empty.news')" class="flex-1" data-home-empty="news" />
                @else
                {{-- الشريط مطلق في صندوقه، فارتفاعه من العمود المجاور (أو حده الأدنى) لا من طول الأخبار --}}
                <div class="home-news-box relative flex-1">
                    <x-marquee id="home-news-track" :label="$tickers['news']" direction="vertical" height="100%" :duration="max(20, $latest->count() * 6)" class="absolute! inset-0" data-home-news-list>
                        @foreach ($latest as $article)
                            <a href="{{ route('media.news.show', $article->slug) }}" wire:navigate data-card data-news-card class="tone-card feed-news-item group">
                                <span class="feed-news-body">
                                    <span data-news-title class="feed-news-title">{{ $article->title }}</span>
                                    <span class="tone-card-muted feed-news-meta">
                                        @if (\App\Support\HomeShowcase::isNew($article))
                                            <span class="badge-new">{{ __('ui.news.new') }}</span>
                                        @endif
                                        @if (filled($article->category))
                                            <span data-news-category class="feed-news-category">{{ $article->category }}</span>
                                        @endif
                                        <span class="inline-flex items-center gap-1.5">
                                            {{ \App\Support\Icon::svg('calendar-days', 'size-4 shrink-0') }}
                                            <time datetime="{{ $article->published_on->toDateString() }}">
                                                {{ \App\Support\Dates::display($article->published_on) }} · {{ \App\Support\Dates::hijri($article->published_on) }}
                                            </time>
                                        </span>
                                    </span>
                                    @if ($article->summary)
                                        <span class="tone-card-muted feed-news-summary">{{ $article->summary }}</span>
                                    @endif
                                </span>
                                <span data-news-thumb class="feed-news-thumb skeleton">
                                    <img src="{{ $article->image ? Storage::disk('public')->url($article->image) : \App\Support\NeutralImage::forPage('media.news.show') }}" alt="" loading="lazy" width="300" height="216"
                                        class="size-full object-cover transition duration-500 group-hover:scale-105">
                                </span>
                            </a>
                        @endforeach
                    </x-marquee>
                </div>

                <a href="{{ route('media.news.index') }}" wire:navigate data-news-all class="feed-news-all">
                    {{ __('ui.feed.all_news') }}
                    {{ \App\Support\Icon::svg('arrow-left', 'size-4') }}
                </a>
                @endif
            </div>
        @endif
    </div>
</section>
