{{--
    المركز الإعلامي (التصور النهائي — Final-Media، المرحلة ج): بلاطات الأقسام بطاقاتٍ ملونة (الأخبار والإعلانات — ملاحظات المدير
    التنفيذي، 25 أكتوبر 2026 — ومكتبة الصور والشركاء من لوحة التصور)، ثم «قياس الرضا» ملوناً بعرض كامل متى فُتح استبيان، ثم الأخبار:
    الأحدث بطاقة كبيرة بصورته، وبجانبه البقية بطاقات ملونة منفصلة تصعد في شريط متحرك (البند P6)، ثم مكتبة الصور شريطاً متحركاً
    (البند R16). والمكتبة المرئية في «مكتبة إعجاز». (تُخزَّن نصاً — MediaController)
--}}
@php
    $tiles = [
        ['key' => 'news', 'icon' => 'newspaper', 'url' => route('media.news.index')],
        ['key' => 'announcements', 'icon' => 'megaphone', 'url' => route('media.announcements')],
        ['key' => 'albums', 'icon' => 'photo', 'url' => route('media.albums.index')],
        ['key' => 'partners', 'icon' => 'user-group', 'url' => route('media.partners')],
        // «منشوراتنا» (طلب صفوان، 3 نوفمبر 2026)
        ['key' => 'social', 'icon' => 'chat-bubble-left-right', 'url' => route('media.social')],
    ];
@endphp

<x-page-hero :title="__('media.title')" />
<x-page-sections />

<section class="pt-8 pb-6">
    <div class="mx-auto max-w-[82.5rem] px-4 sm:px-7">
        <h2 class="sr-only">{{ __('media.tiles.label') }}</h2>
        {{-- خمس بلاطات: صف واحد على الحاسوب، والأخيرة بعرض كامل في عمودي الجوال فلا تبقى وحدها نصف صف --}}
        <ul class="grid grid-cols-2 gap-3.5 lg:grid-cols-5 [&>li:last-child]:col-span-2 lg:[&>li:last-child]:col-span-1" data-reveal-group data-media-tiles>
            @foreach ($tiles as $tile)
                <li class="tone-card">
                    <a href="{{ $tile['url'] }}" wire:navigate class="group flex h-full items-center gap-3.5 px-4.5 py-4.5">
                        <x-icon-tile :icon="$tile['icon']" size="md" />
                        <span class="text-lg font-black sm:text-xl">{{ __('media.tiles.'.$tile['key']) }}</span>
                    </a>
                </li>
            @endforeach
        </ul>

        {{-- مكان بطاقة «قياس الرضا»: يملؤه المتحكم في كل طلب خارج الذاكرة المؤقتة (MediaController::SURVEY_SLOT) --}}
        {{ new \Illuminate\Support\HtmlString(\App\Http\Controllers\MediaController::SURVEY_SLOT) }}
    </div>
</section>

<section class="pb-8" aria-labelledby="latest-news">
    <div class="mx-auto max-w-[82.5rem] px-4 sm:px-7">
        <div class="mb-5 flex items-center justify-between gap-4">
            <x-section-heading id="latest-news" icon="newspaper">{{ __('media.latest_news') }}</x-section-heading>
            @if ($latest->isNotEmpty())
                <a href="{{ route('media.news.index') }}" wire:navigate class="more-link shrink-0">{{ __('site.view_all') }}</a>
            @endif
        </div>

        @if ($latest->isEmpty())
            <x-empty-state icon="newspaper" :message="__('news.list.empty')" />
        @else
            @php($featured = $latest->first())
            <div class="grid gap-4 lg:grid-cols-[1.15fr_1fr]">
                <a href="{{ route('media.news.show', $featured->slug) }}" wire:navigate data-reveal
                    class="group relative flex min-h-[19rem] flex-col justify-end overflow-hidden rounded-[1.25rem] bg-primary p-6 text-white">
                    @if ($featured->image)
                        <img src="{{ Storage::disk('public')->url($featured->image) }}" alt="" loading="lazy" width="760" height="460"
                            class="absolute inset-0 size-full object-cover transition duration-700 group-hover:scale-105">
                    @endif
                    <span class="absolute inset-0 bg-linear-to-b from-primary/10 from-30% to-primary/90" aria-hidden="true"></span>
                    @if (filled($featured->category))
                        <span class="relative self-start rounded-full bg-white px-3 py-0.5 text-xs font-extrabold text-primary-dark">{{ $featured->category }}</span>
                    @endif
                    <span class="relative mt-2 text-2xl leading-snug font-black sm:text-[1.75rem]">{{ $featured->title }}</span>
                    <time datetime="{{ $featured->published_on->toDateString() }}" class="relative mt-1 text-sm text-white/85">{{ \App\Support\Dates::display($featured->published_on) }}</time>
                </a>

                @if ($latest->count() > 1)
                    <x-marquee id="media-news-strip" :label="__('media.latest_news')" direction="vertical" height="19rem" :duration="max(18, ($latest->count() - 1) * 6)">
                        @foreach ($latest->skip(1) as $article)
                            <a href="{{ route('media.news.show', $article->slug) }}" wire:navigate data-card class="tone-card group flex items-center gap-4 p-4">
                                <span class="flex min-w-0 flex-1 flex-col gap-1">
                                    @if (filled($article->category))
                                        <span class="tone-chip">{{ $article->category }}</span>
                                    @endif
                                    <span class="text-lg leading-snug font-black">{{ $article->title }}</span>
                                    <time datetime="{{ $article->published_on->toDateString() }}" class="tone-card-muted text-sm">{{ \App\Support\Dates::display($article->published_on) }}</time>
                                </span>
                                @if ($article->image)
                                    <span class="skeleton block h-[5rem] w-[7.5rem] shrink-0 overflow-hidden rounded-xl">
                                        <img src="{{ Storage::disk('public')->url($article->image) }}" alt="" loading="lazy" width="240" height="160" class="size-full object-cover transition duration-500 group-hover:scale-105">
                                    </span>
                                @endif
                            </a>
                        @endforeach
                    </x-marquee>
                @endif
            </div>
        @endif
    </div>
</section>

@if ($albums->isNotEmpty())
    <section class="band-sky py-8" aria-labelledby="media-albums">
        <div class="mx-auto max-w-[82.5rem] px-4 sm:px-7">
            <div class="mb-5 flex items-center justify-between gap-4">
                <x-section-heading id="media-albums" icon="photo">{{ __('albums.title') }}</x-section-heading>
                <a href="{{ route('media.albums.index') }}" wire:navigate class="more-link shrink-0">{{ __('site.view_all') }}</a>
            </div>

            <x-marquee id="media-albums-strip" :label="__('albums.title')" :duration="max(30, $albums->count() * 7)">
                @foreach ($albums as $album)
                    <a href="{{ route('media.albums.show', $album->slug) }}" wire:navigate class="gallery-tile" data-gallery-tile>
                        @if ($cover = $album->cover())
                            <img src="{{ Storage::disk('public')->url($cover) }}" alt="" loading="lazy" width="240" height="160">
                        @endif
                        <span class="gallery-tile-caption">{{ $album->title }}</span>
                    </a>
                @endforeach
            </x-marquee>
        </div>
    </section>
@endif
