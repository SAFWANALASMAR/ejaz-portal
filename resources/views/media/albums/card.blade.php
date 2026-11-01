{{-- بطاقة ألبوم (الصورة 07): الغلاف (أول صورة)، ثم العنوان، ثم التاريخ وعدد الصور، ودائرة سهم --}}
@php
    use App\Support\Dates;

    $cover = $album->cover();
    $count = count($album->photoList());
@endphp

<a href="{{ route('media.albums.show', $album->slug) }}" wire:navigate
    class="tone-card group flex h-full flex-col overflow-hidden">
    @if ($cover)
        <img src="{{ Storage::disk('public')->url($cover) }}" alt="" loading="lazy" class="aspect-[16/10] w-full object-cover">
    @else
        <span class="tone-card-media flex aspect-[16/10] w-full items-center justify-center" aria-hidden="true">
            {{ \App\Support\Icon::svg('photo', 'size-14') }}
        </span>
    @endif
    <span class="flex flex-1 flex-col p-5">
        <span class="text-lg font-black">{{ $album->title }}</span>
        <span class="tone-card-muted mt-3 flex flex-wrap gap-x-4 gap-y-1 text-sm">
            <span class="inline-flex items-center gap-1.5">
                {{ \App\Support\Icon::svg('calendar', 'size-4 shrink-0', ['aria-hidden' => 'true']) }}
                <time datetime="{{ $album->published_on->toDateString() }}">{{ Dates::display($album->published_on) }}</time>
            </span>
            <span class="inline-flex items-center gap-1.5">
                {{ \App\Support\Icon::svg('photo', 'size-4 shrink-0', ['aria-hidden' => 'true']) }}
                {{ trans_choice('albums.list.photos', $count, ['count' => $count]) }}
            </span>
        </span>
        <span class="mt-auto self-end pt-4" aria-hidden="true">
            <span class="tone-pill size-9 p-0!">
                {{ \App\Support\Icon::svg('arrow-left', 'size-4') }}
            </span>
        </span>
    </span>
</a>
