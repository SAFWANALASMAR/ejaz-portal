{{-- مقطع أحدث الأخبار (الصورة 07): العنوان يميناً و«عرض الكل» يساراً، ثم البطاقات --}}
<section class="section-tint py-12 lg:py-16" aria-labelledby="home-news">
    <div class="container-page">
        <x-section-header id="home-news" :title="__('media.latest_news')" :href="route('media.news.index')" />

        <ul class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($news as $article)
                <li>@include('media.news.card', ['article' => $article])</li>
            @endforeach
        </ul>
    </div>
</section>
