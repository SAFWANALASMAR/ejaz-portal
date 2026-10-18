{{--
    بطاقة عنصر في المكتبة (الصورتان 06 و07): الصورة المصغرة، أو أيقونة القسم على خلفية فاتحة،
    ثم القسم بخط بنفسجي صغير والعنوان، ثم المؤلف والتاريخ بأيقونتيهما، ودائرة سهم.
--}}
@php
    use App\Support\Dates;
@endphp

<a href="{{ route('library.show', [$item->section->value, $item->slug]) }}" wire:navigate
    class="group flex h-full flex-col overflow-hidden rounded-2xl border border-line/70 bg-white shadow-xs transition hover:-translate-y-0.5 hover:shadow-md">
    @if ($item->thumbnail)
        <img src="{{ Storage::disk('public')->url($item->thumbnail) }}" alt="" loading="lazy" class="aspect-[16/10] w-full object-cover">
    @else
        <span class="flex aspect-[16/10] w-full items-center justify-center bg-primary-soft text-primary/70" aria-hidden="true">
            {{ svg('heroicon-o-'.$item->section->icon(), 'size-14') }}
        </span>
    @endif
    <span class="flex flex-1 flex-col p-5">
        <span class="text-sm font-semibold text-accent-dark">{{ $item->section->getLabel() }}</span>
        <span class="mt-1 text-lg font-bold text-ink">{{ $item->title }}</span>
        @if (filled($item->author) || $item->published_on)
            <span class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted">
                @if (filled($item->author))
                    <span class="inline-flex items-center gap-1.5">{{ svg('heroicon-o-user', 'size-4 shrink-0', ['aria-hidden' => 'true']) }}{{ $item->author }}</span>
                @endif
                @if ($item->published_on)
                    <span class="inline-flex items-center gap-1.5">{{ svg('heroicon-o-calendar', 'size-4 shrink-0', ['aria-hidden' => 'true']) }}{{ Dates::display($item->published_on) }}</span>
                @endif
            </span>
        @endif
        {{-- السهم «للأمام» إلى اليسار في الموقع العربي، كما في x-button --}}
        <span class="mt-auto self-end pt-4" aria-hidden="true">
            <span class="inline-flex size-9 items-center justify-center rounded-full bg-primary text-white transition group-hover:bg-primary-dark">
                {{ svg('heroicon-o-arrow-left', 'size-4') }}
            </span>
        </span>
    </span>
</a>
