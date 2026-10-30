{{--
    «إعلانات» و«أخبار الجمعية» جنباً إلى جنب (مرجع المدير التنفيذي — plans/2026-10-30-director-home-reference.md):
    - الإعلانات دوّار (x-carousel) بشريحة أولى لمسابقة البراهين ومجموع جوائزها متى نُشرت، ثم إعلانات اللوحة، و«المزيد».
    - الأخبار قائمة بصورة مصغرة وتاريخ ميلادي وهجري ومقتطف، وشارة «جديد» على ما نُشر في أسبوعه، و«المزيد».
    وكل منهما يختفي إن فرغ. والمحتوى من اللوحة وحدها.
--}}
@php
    $slides = $announcements->count() + ($prize !== null ? 1 : 0);
    $latest = $news->take(3);
@endphp

@if ($slides > 0 || $latest->isNotEmpty())
    <section class="bg-white py-16 lg:py-20" aria-label="{{ __('ui.announcements.title') }} — {{ __('ui.news.title') }}">
        <div @class(['container-page grid gap-10', 'lg:grid-cols-2' => $slides > 0 && $latest->isNotEmpty()])>
            @if ($slides > 0)
                <div data-reveal>
                    <div class="flex items-center justify-between gap-4">
                        <h2 id="home-announcements" class="flex items-center gap-3 text-2xl font-bold text-primary-dark">
                            <x-icon-tile icon="bell" tone="violet" size="sm" />
                            {{ __('ui.announcements.title') }}
                        </h2>
                        <a href="{{ route('media.announcements') }}" wire:navigate class="inline-flex items-center gap-1 text-sm font-semibold text-primary hover:text-primary-dark">
                            {{ __('ui.announcements.more') }}
                            {{ \App\Support\Icon::svg('arrow-left', 'size-4', ['aria-hidden' => 'true']) }}
                        </a>
                    </div>

                    <x-carousel :label="__('ui.announcements.title')" :count="$slides" autoplay :interval="7000" class="mt-5">
                        @if ($prize !== null)
                            <a href="{{ $prize['url'] }}" @navigate($prize['url']) data-carousel-item data-active class="prize-card shine group relative flex min-h-64 flex-col justify-between gap-6 p-7">
                                <span class="bg-pattern-light absolute inset-0 -z-10" aria-hidden="true"></span>
                                <span class="text-3xl leading-snug font-black">{{ $prize['title'] }}</span>
                                <span class="glass flex items-center gap-4 self-start rounded-2xl px-5 py-3">
                                    <x-icon-tile icon="trophy" variant="glass" tone="sky" size="md" class="float-soft" data-loop />
                                    <span>
                                        <span class="block text-sm text-white/85">{{ __('ui.prize.title') }}</span>
                                        <span class="block text-3xl font-black tabular-nums">{{ number_format($prize['total']) }}</span>
                                        <span class="block text-sm text-white/85">{{ __('ui.prize.currency') }}</span>
                                    </span>
                                </span>
                            </a>
                        @endif
                        @foreach ($announcements as $announcement)
                            <div data-carousel-item @if ($prize === null && $loop->first) data-active @endif>
                                @include('media.announcements.card', ['announcement' => $announcement])
                            </div>
                        @endforeach
                    </x-carousel>
                </div>
            @endif

            @if ($latest->isNotEmpty())
                <div data-reveal style="--reveal-delay: 120ms">
                    <div class="flex items-center justify-between gap-4">
                        <h2 id="home-news" class="flex items-center gap-3 text-2xl font-bold text-primary-dark">
                            <x-icon-tile icon="megaphone" tone="blue" size="sm" />
                            {{ __('ui.news.title') }}
                        </h2>
                        <a href="{{ route('media.news.index') }}" wire:navigate class="inline-flex items-center gap-1 text-sm font-semibold text-primary hover:text-primary-dark">
                            {{ __('ui.news.more') }}
                            {{ \App\Support\Icon::svg('arrow-left', 'size-4', ['aria-hidden' => 'true']) }}
                        </a>
                    </div>

                    <ul class="card-glow mt-5 divide-y divide-line/70 overflow-hidden">
                        @foreach ($latest as $article)
                            <li>
                                <a href="{{ route('media.news.show', $article->slug) }}" wire:navigate class="group flex gap-4 p-4 transition hover:bg-surface sm:p-5">
                                    <span class="skeleton relative block aspect-[4/3] w-28 shrink-0 overflow-hidden rounded-2xl sm:w-36">
                                        @if ($article->image)
                                            <img src="{{ Storage::disk('public')->url($article->image) }}" alt="" loading="lazy" width="288" height="216"
                                                class="size-full object-cover transition duration-500 group-hover:scale-105">
                                        @endif
                                    </span>
                                    <span class="min-w-0">
                                        <span class="flex flex-wrap items-center gap-2">
                                            @if (\App\Support\HomeShowcase::isNew($article))
                                                <span class="badge-new">{{ __('ui.news.new') }}</span>
                                            @endif
                                            @if ($article->published_on)
                                                <time datetime="{{ $article->published_on->toDateString() }}" class="text-sm text-muted">
                                                    {{ \App\Support\Dates::display($article->published_on) }}
                                                    <span aria-hidden="true">·</span>
                                                    {{ \App\Support\Dates::hijri($article->published_on) }}
                                                </time>
                                            @endif
                                        </span>
                                        <span class="mt-1 line-clamp-2 block font-bold leading-7 text-primary-dark group-hover:text-primary">{{ $article->title }}</span>
                                        @if ($article->summary)
                                            <span class="mt-1 line-clamp-2 block text-sm leading-6 text-muted">{{ $article->summary }}</span>
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
