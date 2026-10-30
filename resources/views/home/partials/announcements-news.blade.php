{{--
    عمودان (التصور النهائي — Final-Home، وملاحظات المدير التنفيذي 31 أكتوبر 01:28): في الأول «إعلانات» دوّاراً أطول قليلاً بشريحة
    أولى لمسابقة البراهين ومجموع جوائزها متى نُشرت ثم إعلانات اللوحة. وفي الثاني «أخبار الجمعية» بطاقات بتدرج يشتد خبراً بعد خبر
    (.news-tint)، بعنوان وتاريخ ميلادي وهجري ومقتطف وصورة مصغرة، وشارة «جديد» على ما نُشر في أسبوعه. ولكل منهما «المزيد».
    و«مستجدات الجمعية» صارت شريطي المرئيات وأغلفة الكتب (home/partials/strips). وكل ما فرغ يختفي، والمحتوى من اللوحة وحدها.
--}}
@php
    $slides = $announcements->count() + ($prize !== null ? 1 : 0);
    $latest = $news->take(4);
    $first = $slides > 0;
@endphp

@if ($first || $latest->isNotEmpty())
    <section class="home-band" data-band="3" aria-label="{{ $tickers['announcements'] }} — {{ $tickers['news'] }}">
        <div @class(['mx-auto grid max-w-[82.5rem] gap-8 px-4 pt-12 pb-16 sm:px-7', 'lg:grid-cols-2' => $first && $latest->isNotEmpty()])>
            @if ($first)
                <div class="flex flex-col gap-4" data-reveal>
                    @if ($slides > 0)
                        <div class="flex items-center justify-between gap-4">
                            <h2 id="home-announcements" class="flex items-center gap-2.5 text-[1.875rem] font-black text-primary-dark">
                                {{ $tickers['announcements'] }}
                                <span class="icon-line">{{ \App\Support\Icon::svg('bell', 'size-[1.875rem]', ['aria-hidden' => 'true']) }}</span>
                            </h2>
                            <a href="{{ route('media.announcements') }}" wire:navigate class="more-link">{{ __('ui.announcements.more') }}</a>
                        </div>

                        <x-carousel :label="$tickers['announcements']" :count="$slides" autoplay :interval="7000" :status="false">
                            @if ($prize !== null)
                                <div data-carousel-item data-active>
                                <a href="{{ $prize['url'] }}" @navigate($prize['url']) class="announcement-feature group relative flex min-h-[17.5rem] flex-col gap-2 overflow-hidden rounded-[1.25rem] px-7.5 py-7 text-white">
                                    <svg class="pointer-events-none absolute -top-15 -end-12 size-75 opacity-15" viewBox="0 0 200 200" fill="none" stroke="currentColor" aria-hidden="true">
                                        <path d="M100 10 L190 100 L100 190 L10 100 Z" /><path d="M100 40 L160 100 L100 160 L40 100 Z" /><circle cx="100" cy="100" r="40" />
                                    </svg>
                                    <span class="relative text-3xl leading-snug font-black sm:text-[2.5rem]">{{ $prize['title'] }}</span>
                                    <span data-prize-total class="relative mt-auto flex items-center gap-3.5 self-end rounded-full bg-linear-to-br from-white to-[var(--concept-sky)] py-2.5 ps-3.5 pe-6 text-primary-dark shadow-[0_16px_30px_-14px_rgba(0,0,0,.6)]">
                                        <span class="inline-flex size-13 items-center justify-center rounded-full bg-primary-dark text-brand-light">{{ \App\Support\Icon::svg('trophy', 'size-7', ['aria-hidden' => 'true']) }}</span>
                                        <span class="flex flex-col leading-[1.05]">
                                            <span class="text-[13px]">{{ __('ui.prize.title') }}</span>
                                            <span class="text-[2rem] font-black tabular-nums">{{ number_format($prize['total']) }}</span>
                                            <span class="text-[13px]">{{ __('ui.prize.currency') }}</span>
                                        </span>
                                    </span>
                                </a>
                                </div>
                            @endif
                            @foreach ($announcements as $announcement)
                                <div data-carousel-item @if ($prize === null && $loop->first) data-active @endif>
                                    @include('media.announcements.card', ['announcement' => $announcement])
                                </div>
                            @endforeach
                        </x-carousel>
                    @endif

                </div>
            @endif

            @if ($latest->isNotEmpty())
                <div class="flex flex-col gap-4" data-reveal style="--reveal-delay: 120ms">
                    <div class="flex items-center justify-between gap-4">
                        <h2 id="home-news" class="flex items-center gap-2.5 text-[1.875rem] font-black text-primary-dark">
                            {{ $tickers['news'] }}
                            <span class="icon-line">{{ \App\Support\Icon::svg('megaphone', 'size-[1.875rem]', ['aria-hidden' => 'true']) }}</span>
                        </h2>
                        <a href="{{ route('media.news.index') }}" wire:navigate class="more-link">{{ __('ui.news.more') }}</a>
                    </div>

                    <ul class="flex flex-col gap-3" data-home-news-list>
                        @foreach ($latest as $article)
                            <li>
                                <a href="{{ route('media.news.show', $article->slug) }}" wire:navigate data-step="{{ $loop->index }}"
                                    class="news-tint card-lift group flex gap-4.5 px-4.5 py-4">
                                    <span class="flex min-w-0 flex-1 flex-col gap-1.5">
                                        <span class="text-[19px] leading-snug font-black text-primary-dark group-hover:text-primary">{{ $article->title }}</span>
                                        <span class="flex flex-wrap items-center gap-2 text-sm text-[var(--concept-text)]">
                                            @if (\App\Support\HomeShowcase::isNew($article))
                                                <span class="badge-new">{{ __('ui.news.new') }}</span>
                                            @endif
                                            <time datetime="{{ $article->published_on->toDateString() }}">
                                                {{ \App\Support\Dates::display($article->published_on) }} · {{ \App\Support\Dates::hijri($article->published_on) }}
                                            </time>
                                        </span>
                                        @if ($article->summary)
                                            <span class="line-clamp-2 text-[15px] leading-[1.7] text-[var(--concept-text)]">{{ $article->summary }}</span>
                                        @endif
                                    </span>
                                    <span class="skeleton block h-[6.75rem] w-[9.375rem] shrink-0 overflow-hidden rounded-xl max-sm:h-20 max-sm:w-28">
                                        @if ($article->image)
                                            <img src="{{ Storage::disk('public')->url($article->image) }}" alt="" loading="lazy" width="300" height="216"
                                                class="size-full object-cover transition duration-500 group-hover:scale-105">
                                        @endif
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </section>
@endif
