{{-- بطاقة ألبوم (الصورة 07): الغلاف (أول صورة)، ثم العنوان، ثم التاريخ وعدد الصور، ودائرة سهم --}}
@php
    use App\Support\Dates;

    $cover = $album->cover();
    $count = count($album->photoList());
@endphp

<a href="{{ route('media.albums.show', $album->slug) }}" wire:navigate
    class="group flex h-full flex-col overflow-hidden rounded-2xl border border-line/70 bg-white shadow-xs transition hover:-translate-y-0.5 hover:shadow-md">
    @if ($cover)
        <img src="{{ Storage::disk('public')->url($cover) }}" alt="" loading="lazy" class="aspect-[16/10] w-full object-cover">
    @else
        <span class="flex aspect-[16/10] w-full items-center justify-center bg-primary-soft text-primary/70" aria-hidden="true">
            {{ svg('heroicon-o-photo', 'size-14') }}
        </span>
    @endif
    <span class="flex flex-1 flex-col p-5">
        <span class="text-lg font-bold text-ink">{{ $album->title }}</span>
        <span class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted">
            <span class="inline-flex items-center gap-1.5">
                {{ svg('heroicon-o-calendar', 'size-4 shrink-0', ['aria-hidden' => 'true']) }}
                <time datetime="{{ $album->published_on->toDateString() }}">{{ Dates::display($album->published_on) }}</time>
            </span>
            <span class="inline-flex items-center gap-1.5">
                {{ svg('heroicon-o-photo', 'size-4 shrink-0', ['aria-hidden' => 'true']) }}
                {{ trans_choice('albums.list.photos', $count, ['count' => $count]) }}
            </span>
        </span>
        <span class="mt-auto self-end pt-4" aria-hidden="true">
            <span class="inline-flex size-9 items-center justify-center rounded-full bg-primary text-white transition group-hover:bg-primary-dark">
                {{ svg('heroicon-o-arrow-left', 'size-4') }}
            </span>
        </span>
    </span>
</a>
