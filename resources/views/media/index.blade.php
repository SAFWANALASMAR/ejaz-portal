{{--
    المركز الإعلامي (الصورة 05): رأس القسم، ثم بلاطات الأخبار ومكتبة الصور ومكتبة الفيديو والشركاء،
    ثم «قياس الرضا» بعرض كامل متى بُنيت وحدته، ثم أحدث الأخبار. (تُخزَّن نصاً — MediaController)
--}}
@php
    use Illuminate\Support\Facades\Route;

    // مكتبة الفيديو هي المكتبة المرئية نفسها، فلا تُدار المقاطع في مكانين (decisions.md)
    $tiles = [
        ['key' => 'news', 'icon' => 'newspaper', 'url' => route('media.news.index')],
        ['key' => 'albums', 'icon' => 'photo', 'url' => route('media.albums.index')],
        ['key' => 'videos', 'icon' => 'play-circle', 'url' => route('library.section', 'videos')],
        ['key' => 'partners', 'icon' => 'building-office-2', 'url' => route('media.partners')],
    ];
@endphp

<x-page-hero :title="__('media.title')" />

<section class="py-10 sm:py-14">
    <div class="container-page">
        <h2 class="sr-only">{{ __('media.tiles.label') }}</h2>
        <ul class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            @foreach ($tiles as $tile)
                <li>
                    <a href="{{ $tile['url'] }}"
                        class="flex h-full flex-col items-center gap-3 rounded-2xl border border-line/70 bg-white p-6 text-center shadow-xs transition hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-md">
                        <span class="text-accent" aria-hidden="true">{{ svg('heroicon-o-'.$tile['icon'], 'size-9') }}</span>
                        <span class="font-semibold text-ink">{{ __('media.tiles.'.$tile['key']) }}</span>
                    </a>
                </li>
            @endforeach
        </ul>

        {{-- «قياس الرضا» بعرض كامل كما في الصورة 05، ومتى فُتح استبيان عام (كبطاقة الخدمات)، فلا رابط إلى 404 --}}
        @if (Route::has('surveys.index') && \App\Models\Survey::hasOpenGeneral())
            <a href="{{ route('surveys.index') }}"
                class="mt-4 flex items-center justify-center gap-3 rounded-2xl border border-line/70 bg-white p-5 font-semibold text-ink shadow-xs transition hover:border-primary/40 hover:shadow-md">
                <span class="text-accent" aria-hidden="true">{{ svg('heroicon-o-chart-bar', 'size-7') }}</span>
                {{ __('media.tiles.surveys') }}
            </a>
        @endif
    </div>
</section>

<section class="bg-surface py-12 sm:py-16" aria-labelledby="latest-news">
    <div class="container-page">
        <div class="flex items-end justify-between gap-4">
            <x-section-heading id="latest-news">{{ __('media.latest_news') }}</x-section-heading>
            @if ($latest->isNotEmpty())
                <a href="{{ route('media.news.index') }}" class="inline-flex items-center gap-1 text-sm font-semibold text-primary hover:text-primary-dark">
                    {{ __('site.view_all') }}
                    {{ svg('heroicon-o-arrow-left', 'size-4', ['aria-hidden' => 'true']) }}
                </a>
            @endif
        </div>

        @if ($latest->isEmpty())
            <p class="mt-6 text-muted">{{ __('news.list.empty') }}</p>
        @else
            <ul class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($latest as $article)
                    <li>@include('media.news.card', ['article' => $article])</li>
                @endforeach
            </ul>
        @endif
    </div>
</section>
