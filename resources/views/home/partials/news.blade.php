{{-- مقطع أحدث الأخبار (الصورة 07): العنوان يميناً و«عرض الكل» يساراً، ثم البطاقات --}}
<section class="py-12 lg:py-16" aria-labelledby="home-news">
    <div class="container-page">
        <div class="flex items-end justify-between gap-4">
            <x-section-heading id="home-news">{{ __('media.latest_news') }}</x-section-heading>
            <a href="{{ route('media.news.index') }}" class="inline-flex items-center gap-1 text-sm font-semibold text-primary hover:text-primary-dark">
                {{ __('site.view_all') }}
                {{ svg('heroicon-o-arrow-left', 'size-4', ['aria-hidden' => 'true']) }}
            </a>
        </div>

        <ul class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($news as $article)
                <li>@include('media.news.card', ['article' => $article])</li>
            @endforeach
        </ul>
    </div>
</section>
