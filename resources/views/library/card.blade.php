{{--
    بطاقة عنصر في المكتبة (الصورتان 06 و07): الصورة المصغرة، أو أيقونة القسم على خلفية فاتحة،
    ثم القسم بخط بنفسجي صغير والعنوان، ثم المؤلف والتاريخ بأيقونتيهما، ودائرة سهم.
--}}
@php
    use App\Support\Dates;
@endphp

<a href="{{ route('library.show', [$item->section->value, $item->slug]) }}" wire:navigate
    class="group flex h-full flex-col overflow-hidden card-lift">
    @if (($number ?? null) !== null)
        <x-video-number :number="$number" />
    @endif
    {{-- المرفوعة، وإلا صورة مقطع يوتيوب المصغرة (LibraryItem::thumbnailUrl) --}}
    @if ($thumbnail = $item->thumbnailUrl())
        <span class="video-frame relative block">
            <span class="skeleton block overflow-hidden"><img src="{{ $thumbnail }}" alt="" loading="lazy" referrerpolicy="no-referrer" class="aspect-[16/10] w-full bg-primary-soft object-cover transition duration-700 group-hover:scale-105"></span>
            @if ($item->youtubeId())
                <span class="absolute inset-0 flex items-center justify-center" aria-hidden="true">
                    <span class="play-halo inline-flex size-16 items-center justify-center rounded-full bg-white/95 text-primary-dark transition group-hover:scale-110">
                        {{ \App\Support\Icon::solid('play', 'size-7') }}
                    </span>
                </span>
            @endif
        </span>
    @else
        <span class="flex aspect-[16/10] w-full items-center justify-center bg-primary-soft text-primary/70" aria-hidden="true">
            {{ \App\Support\Icon::svg($item->section->icon(), 'size-14') }}
        </span>
    @endif
    <span class="flex flex-1 flex-col p-5">
        <span class="text-sm font-extrabold text-accent">{{ $item->section->getLabel() }}</span>
        <span class="mt-1 text-lg leading-snug font-black text-primary-dark">{{ $item->title }}</span>
        @if ($item->isReadOnly())
            <span class="mt-2 inline-flex w-fit items-center gap-1.5 rounded-full bg-primary-soft px-2.5 py-0.5 text-xs font-semibold text-primary-dark">
                {{ svg('heroicon-o-book-open', 'size-4 shrink-0', ['aria-hidden' => 'true']) }}{{ __('library.item.read_only_short') }}
            </span>
        @endif
        @if (filled($item->author) || $item->published_on)
            <span class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted">
                @if (filled($item->author))
                    <span class="inline-flex items-center gap-1.5">{{ \App\Support\Icon::svg('user', 'size-4 shrink-0', ['aria-hidden' => 'true']) }}{{ $item->author }}</span>
                @endif
                @if ($item->published_on)
                    <span class="inline-flex items-center gap-1.5">{{ \App\Support\Icon::svg('calendar', 'size-4 shrink-0', ['aria-hidden' => 'true']) }}{{ Dates::display($item->published_on) }}</span>
                @endif
            </span>
        @endif
        {{-- السهم «للأمام» إلى اليسار في الموقع العربي، كما في x-button --}}
        <span class="mt-auto self-end pt-4" aria-hidden="true">
            <span class="inline-flex size-9 items-center justify-center rounded-full bg-primary text-white transition group-hover:bg-primary-dark">
                {{ \App\Support\Icon::svg('arrow-left', 'size-4') }}
            </span>
        </span>
    </span>
</a>
