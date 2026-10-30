{{--
    المركز الإعلامي: الأخبار والإعلانات وحدهما (ملاحظات المدير التنفيذي، 25 أكتوبر 2026)، ثم أحدث الأخبار. والمكتبة
    المرئية في «مكتبة إعجاز»، ومكتبة الصور والشركاء باقيتان بروابطهما بلا بلاطة. (تُخزَّن نصاً — MediaController)
--}}
@php
    $tiles = [
        ['key' => 'news', 'icon' => 'newspaper', 'url' => route('media.news.index')],
        ['key' => 'announcements', 'icon' => 'megaphone', 'url' => route('media.announcements')],
    ];
@endphp

<x-page-hero :title="__('media.title')" />

<section class="py-10 sm:py-14">
    <div class="container-page">
        <h2 class="sr-only">{{ __('media.tiles.label') }}</h2>
        <ul class="grid gap-4 sm:grid-cols-2" data-reveal-group>
            @foreach ($tiles as $tile)
                <li><x-section-tile :index="$loop->index" :href="$tile['url']" :icon="$tile['icon']" :label="__('media.tiles.'.$tile['key'])" /></li>
            @endforeach
        </ul>

        {{-- مكان بطاقة «قياس الرضا»: يملؤه المتحكم في كل طلب خارج الذاكرة المؤقتة (MediaController::SURVEY_SLOT) --}}
        {{ new \Illuminate\Support\HtmlString(\App\Http\Controllers\MediaController::SURVEY_SLOT) }}
    </div>
</section>

{{-- الأخبار (التصور النهائي — Final-Media): الخبر الأحدث بطاقة كبيرة بصورته، وبجانبه قائمة البقية بصورة مصغرة --}}
<section class="pb-14" aria-labelledby="latest-news">
    <div class="mx-auto max-w-[82.5rem] px-4 sm:px-7">
        <x-section-header id="latest-news" :title="__('media.latest_news')" :href="$latest->isNotEmpty() ? route('media.news.index') : null" />

        @if ($latest->isEmpty())
            <x-empty-state icon="newspaper" :message="__('news.list.empty')" class="mt-6" />
        @else
            @php($featured = $latest->first())
            <div class="mt-6 grid gap-5 lg:grid-cols-[1.15fr_1fr]" data-reveal-group>
                <a href="{{ route('media.news.show', $featured->slug) }}" wire:navigate
                    class="group relative flex min-h-[19rem] flex-col justify-end overflow-hidden rounded-[1.25rem] bg-primary-dark p-6 text-white">
                    @if ($featured->image)
                        <img src="{{ Storage::disk('public')->url($featured->image) }}" alt="" loading="lazy" width="760" height="460"
                            class="absolute inset-0 size-full object-cover transition duration-700 group-hover:scale-105">
                    @endif
                    <span class="absolute inset-0 bg-linear-to-b from-primary-dark/10 from-30% to-primary-deep/90" aria-hidden="true"></span>
                    @if (filled($featured->category))
                        <span class="relative self-start rounded-full bg-white px-3 py-0.5 text-xs font-extrabold text-accent">{{ $featured->category }}</span>
                    @endif
                    <span class="relative mt-2 text-2xl leading-snug font-black sm:text-[1.75rem]">{{ $featured->title }}</span>
                    <time datetime="{{ $featured->published_on->toDateString() }}" class="relative mt-1 text-sm text-white/85">{{ \App\Support\Dates::display($featured->published_on) }}</time>
                </a>

                @if ($latest->count() > 1)
                    <ul class="concept-card divide-y divide-[#EEF3F8] px-4">
                        @foreach ($latest->skip(1)->take(3) as $article)
                            <li>
                                <a href="{{ route('media.news.show', $article->slug) }}" wire:navigate class="group flex items-center gap-4 py-4">
                                    <span class="flex min-w-0 flex-1 flex-col gap-1">
                                        @if (filled($article->category))
                                            <span class="self-start rounded-full bg-primary-soft px-2.5 py-0.5 text-xs font-extrabold text-accent">{{ $article->category }}</span>
                                        @endif
                                        <span class="text-lg leading-snug font-black text-primary-dark group-hover:text-primary">{{ $article->title }}</span>
                                        <time datetime="{{ $article->published_on->toDateString() }}" class="text-sm text-muted">{{ \App\Support\Dates::display($article->published_on) }}</time>
                                    </span>
                                    <span class="skeleton block h-[5.5rem] w-[8.5rem] shrink-0 overflow-hidden rounded-xl">
                                        @if ($article->image)
                                            <img src="{{ Storage::disk('public')->url($article->image) }}" alt="" loading="lazy" width="272" height="176" class="size-full object-cover transition duration-500 group-hover:scale-105">
                                        @endif
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif
    </div>
</section>
