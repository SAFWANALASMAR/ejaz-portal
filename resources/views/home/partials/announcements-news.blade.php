{{--
    «إعلانات» و«أخبار الجمعية» متجاورتان بالارتفاع نفسه بلا فراغ (أمر التصور النهائي، النسخة 63 — البنود R7 وH7 وH8): نحو 320 بكسل على
    الحاسوب و260 على الجوال. في الأولى دوّار بشريحة أولى لمسابقة البراهين ومجموع جوائزها من جدولها يعدّ تصاعدياً حين يظهر
    (data-count-to — motion.js، والرقم كاملاً في الصفحة بلا سكربت)، ثم إعلانات اللوحة. وفي الثانية شريط يصعد بلا انقطاع (x-marquee
    عمودي)، كل خبر بطاقة منفصلة بتدرج أزرق (.tone-card)، ويتوقف بالمرور والتركيز وزر الإيقاف ولمن طلب تقليل الحركة. ولكل منهما
    «المزيد». وكل ما فرغ يختفي، والمحتوى من اللوحة وحدها، والعنوانان من «محتوى الرئيسية» (tickers).
--}}
@php
    $slides = $announcements->count() + ($prize !== null ? 1 : 0);
    $latest = $news->take(6);
    $first = $slides > 0;
@endphp

<section class="home-band" data-band="{{ $band }}" aria-label="{{ $tickers['announcements'] }} — {{ $tickers['news'] }}">
    <div @class(['mx-auto grid max-w-[82.5rem] gap-6 px-4 pt-6 pb-7 sm:px-7 lg:gap-8 lg:pt-7 lg:pb-8', 'lg:grid-cols-2' => $first && $latest->isNotEmpty()])>
        @if ($first)
            <div class="flex min-w-0 flex-col gap-3" data-reveal>
                <div class="flex items-center justify-between gap-4">
                    <h2 id="home-announcements" class="section-heading-title flex items-center gap-2.5 font-black text-primary-dark">
                        <x-icon-tile icon="bell" size="sm" shade="medium" class="section-heading-icon" />
                        {{ $tickers['announcements'] }}
                    </h2>
                    <a href="{{ route('media.announcements') }}" wire:navigate class="more-link">{{ __('ui.announcements.more') }}<span class="sr-only">: {{ $tickers['announcements'] }}</span></a>
                </div>

                <x-carousel :label="$tickers['announcements']" :count="$slides" autoplay :interval="7000" :status="false" class="home-announcements">
                    @if ($prize !== null)
                        <div data-carousel-item data-active>
                            <a href="{{ $prize['url'] }}" @navigate($prize['url']) class="announcement-feature home-feed-box group relative flex flex-col gap-2 overflow-hidden rounded-[1.25rem] px-6 py-6 text-white lg:px-7.5 lg:py-7">
                                <svg class="pointer-events-none absolute -top-15 -end-12 size-75 opacity-15" viewBox="0 0 200 200" fill="none" stroke="currentColor" aria-hidden="true">
                                    <path d="M100 10 L190 100 L100 190 L10 100 Z" /><path d="M100 40 L160 100 L100 160 L40 100 Z" /><circle cx="100" cy="100" r="40" />
                                </svg>
                                <span class="relative text-[1.625rem] leading-snug font-black sm:text-[2.5rem]">{{ $prize['title'] }}</span>
                                <span data-prize-total class="relative mt-auto flex items-center gap-3.5 self-end rounded-full bg-linear-to-br from-white to-[var(--concept-sky)] py-2.5 ps-3.5 pe-6 text-primary-dark shadow-[0_16px_30px_-14px_rgba(0,0,0,.6)]">
                                    <x-icon-tile icon="trophy" size="sm" shade="dark" />
                                    <span class="flex flex-col leading-[1.05]">
                                        <span class="text-[13px]">{{ __('ui.prize.title') }}</span>
                                        {{-- العدّ داخل منطقة الدوّار الحية كان يُقرأ رقماً رقماً: الظاهر مخفي عن قارئ الشاشة، والنهائي له ثابت --}}
                                        <span class="text-[2rem] font-black tabular-nums" aria-hidden="true" data-count-to="{{ $prize['total'] }}">{{ number_format($prize['total']) }}</span>
                                        <span class="sr-only">{{ number_format($prize['total']) }}</span>
                                        <span class="text-[13px]">{{ __('ui.prize.currency') }}</span>
                                    </span>
                                </span>
                            </a>
                        </div>
                    @endif
                    @foreach ($announcements as $announcement)
                        <div data-carousel-item @if ($prize === null && $loop->first) data-active @endif class="home-feed-box">
                            @include('media.announcements.card', ['announcement' => $announcement])
                        </div>
                    @endforeach
                </x-carousel>
            </div>
        @endif

        @if ($latest->isNotEmpty())
            <div class="flex min-w-0 flex-col gap-3" data-reveal style="--reveal-delay: 120ms">
                <div class="flex items-center justify-between gap-4">
                    <h2 id="home-news" class="section-heading-title flex items-center gap-2.5 font-black text-primary-dark">
                        <x-icon-tile icon="megaphone" size="sm" shade="medium" class="section-heading-icon" />
                        {{ $tickers['news'] }}
                    </h2>
                    <a href="{{ route('media.news.index') }}" wire:navigate class="more-link">{{ __('ui.news.more') }}<span class="sr-only">: {{ $tickers['news'] }}</span></a>
                </div>

                {{-- الشريط مطلق في صندوقه، فارتفاعه من العمود المجاور (أو حده الأدنى) لا من طول الأخبار --}}
                <div class="home-news-box relative flex-1">
                    <x-marquee id="home-news-track" :label="$tickers['news']" direction="vertical" height="100%" :duration="max(20, $latest->count() * 6)" class="absolute! inset-0" data-home-news-list>
                        @foreach ($latest as $article)
                            <a href="{{ route('media.news.show', $article->slug) }}" wire:navigate data-card data-news-card class="tone-card group flex gap-3.5 p-3.5 lg:gap-4.5">
                                <span class="flex min-w-0 flex-1 flex-col gap-1">
                                    <span class="line-clamp-2 text-[17px] leading-snug font-black lg:text-[19px]">{{ $article->title }}</span>
                                    <span class="tone-card-muted flex flex-wrap items-center gap-2 text-sm">
                                        @if (\App\Support\HomeShowcase::isNew($article))
                                            <span class="badge-new">{{ __('ui.news.new') }}</span>
                                        @endif
                                        <time datetime="{{ $article->published_on->toDateString() }}">
                                            {{ \App\Support\Dates::display($article->published_on) }} · {{ \App\Support\Dates::hijri($article->published_on) }}
                                        </time>
                                    </span>
                                    @if ($article->summary)
                                        <span class="tone-card-muted line-clamp-2 text-[14px] leading-[1.7] lg:text-[15px]">{{ $article->summary }}</span>
                                    @endif
                                </span>
                                <span class="skeleton block h-20 w-28 shrink-0 overflow-hidden rounded-xl lg:h-[6.75rem] lg:w-[9.375rem]">
                                    @if ($article->image)
                                        <img src="{{ Storage::disk('public')->url($article->image) }}" alt="" loading="lazy" width="300" height="216"
                                            class="size-full object-cover transition duration-500 group-hover:scale-105">
                                    @endif
                                </span>
                            </a>
                        @endforeach
                    </x-marquee>
                </div>
            </div>
        @endif
    </div>
</section>
