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
