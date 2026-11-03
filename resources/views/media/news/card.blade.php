{{--
    بطاقة خبر (الصورتان 05 و07): الغلاف، أو أيقونة على خلفية فاتحة، ثم التصنيف والتاريخ بخط صغير،
    ثم العنوان والملخص، ودائرة سهم.
--}}
@php
    use App\Support\Dates;
@endphp

<a href="{{ route('media.news.show', $article->slug) }}" wire:navigate
    class="tone-card group flex h-full flex-col overflow-hidden">
    @if ($article->image)
        <span class="skeleton block overflow-hidden"><img src="{{ Storage::disk('public')->url($article->image) }}" alt="" loading="lazy" class="aspect-[16/10] w-full object-cover transition duration-700 group-hover:scale-105"></span>
    @else
        <span class="tone-card-media flex aspect-[16/10] w-full items-center justify-center" aria-hidden="true">
            {{ \App\Support\Icon::svg('newspaper', 'size-14') }}
        </span>
    @endif
    <span class="flex flex-1 flex-col p-5">
        <span class="flex flex-wrap gap-x-3 gap-y-1 text-sm">
            @if (filled($article->category))
                <span class="tone-chip">{{ $article->category }}</span>
            @endif
            <time datetime="{{ $article->published_on->toDateString() }}" class="tone-card-muted">{{ Dates::display($article->published_on) }}</time>
        </span>
        <span class="mt-1.5 line-clamp-2 text-lg leading-snug font-black">{{ $article->title }}</span>
        @if (filled($article->summary))
            <span class="tone-card-muted mt-2 line-clamp-3 text-sm leading-6">{{ $article->summary }}</span>
        @endif
        {{-- السهم «للأمام» إلى اليسار في الموقع العربي، كما في x-button --}}
        <span class="mt-auto self-end pt-4" aria-hidden="true">
            <span class="tone-pill size-9 p-0!">
                {{ \App\Support\Icon::svg('arrow-left', 'size-4') }}
            </span>
        </span>
    </span>
</a>
