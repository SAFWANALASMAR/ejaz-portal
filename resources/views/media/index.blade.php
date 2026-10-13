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
                <li><x-section-tile :href="$tile['url']" :icon="$tile['icon']" :label="__('media.tiles.'.$tile['key'])" /></li>
            @endforeach
        </ul>

        {{-- مكان بطاقة «قياس الرضا»: يملؤه المتحكم في كل طلب خارج الذاكرة المؤقتة (MediaController::SURVEY_SLOT) --}}
        {{ new \Illuminate\Support\HtmlString(\App\Http\Controllers\MediaController::SURVEY_SLOT) }}
    </div>
</section>

<section class="bg-surface py-12 sm:py-16" aria-labelledby="latest-news">
    <div class="container-page">
        <x-section-header id="latest-news" :title="__('media.latest_news')" :href="$latest->isNotEmpty() ? route('media.news.index') : null" />

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
