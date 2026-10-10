{{--
    صفحة الخبر: رأس بغلافه، ثم التصنيف والتاريخ والمشاركة، ثم المحتوى بالمقاطع المرنة، ثم المعرض،
    ثم أخبار أخرى. (تُخزَّن نصاً — NewsController)
--}}
@php
    use App\Support\Dates;

    $gallery = $article->galleryImages();
@endphp

<x-page-hero :title="$article->title" :summary="$article->summary"
    :image="$article->image ? Storage::disk('public')->url($article->image) : null" />

<section class="pt-10 sm:pt-12">
    <div class="container-page flex flex-wrap items-center justify-between gap-4 border-b border-line/70 pb-6">
        <p class="flex flex-wrap gap-x-4 gap-y-1 text-sm">
            @if (filled($article->category))
                <a href="{{ route('media.news.index', ['category' => $article->category]) }}" class="font-semibold text-accent-dark hover:underline">{{ $article->category }}</a>
            @endif
            <time datetime="{{ $article->published_on->toDateString() }}" class="text-muted">{{ Dates::display($article->published_on) }}</time>
        </p>

        @include('media.news.share', ['article' => $article])
    </div>
</section>

<x-content-blocks :blocks="$article->blocks" />

@if ($gallery !== [])
    <section class="py-10 sm:py-12" aria-labelledby="gallery">
        <div class="container-page">
            <x-section-heading id="gallery">{{ __('news.fields.gallery') }}</x-section-heading>
            <div class="mt-8">
                <x-gallery :images="collect($gallery)->values()->map(fn (string $path, int $i): array => [
                    'src' => Storage::disk('public')->url($path),
                    'alt' => __('news.article.gallery_alt', ['number' => $i + 1, 'title' => $article->title]),
                ])->all()" />
            </div>
        </div>
    </section>
@endif

@if ($more->isNotEmpty())
    <section class="bg-surface py-12 sm:py-16" aria-labelledby="more-news">
        <div class="container-page">
            <x-section-heading id="more-news">{{ __('news.article.more') }}</x-section-heading>
            <ul class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($more as $other)
                    <li>@include('media.news.card', ['article' => $other])</li>
                @endforeach
            </ul>
        </div>
    </section>
@endif
