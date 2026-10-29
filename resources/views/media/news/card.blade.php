{{--
    بطاقة خبر (الصورتان 05 و07): الغلاف، أو أيقونة على خلفية فاتحة، ثم التصنيف والتاريخ بخط صغير،
    ثم العنوان والملخص، ودائرة سهم.
--}}
@php
    use App\Support\Dates;
@endphp

<a href="{{ route('media.news.show', $article->slug) }}" wire:navigate
    class="group flex h-full flex-col overflow-hidden rounded-2xl border border-line/70 bg-white shadow-xs transition hover:-translate-y-0.5 hover:shadow-md">
    @if ($article->image)
        <img src="{{ Storage::disk('public')->url($article->image) }}" alt="" loading="lazy" class="aspect-[16/10] w-full object-cover">
    @else
        <span class="flex aspect-[16/10] w-full items-center justify-center bg-primary-soft text-primary/70" aria-hidden="true">
            {{ \App\Support\Icon::svg('newspaper', 'size-14') }}
        </span>
    @endif
    <span class="flex flex-1 flex-col p-5">
        <span class="flex flex-wrap gap-x-3 gap-y-1 text-sm">
            @if (filled($article->category))
                <span class="font-semibold text-accent-dark">{{ $article->category }}</span>
            @endif
            <time datetime="{{ $article->published_on->toDateString() }}" class="text-muted">{{ Dates::display($article->published_on) }}</time>
        </span>
        <span class="mt-1 text-lg font-bold text-ink">{{ $article->title }}</span>
        @if (filled($article->summary))
            <span class="mt-2 line-clamp-3 text-sm leading-6 text-muted">{{ $article->summary }}</span>
        @endif
        {{-- السهم «للأمام» إلى اليسار في الموقع العربي، كما في x-button --}}
        <span class="mt-auto self-end pt-4" aria-hidden="true">
            <span class="inline-flex size-9 items-center justify-center rounded-full bg-primary text-white transition group-hover:bg-primary-dark">
                {{ \App\Support\Icon::svg('arrow-left', 'size-4') }}
            </span>
        </span>
    </span>
</a>
