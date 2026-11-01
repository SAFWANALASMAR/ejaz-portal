{{--
    بطاقة عنصر في المكتبة (الصورتان 06 و07): الصورة المصغرة، أو أيقونة القسم على خلفية فاتحة،
    ثم القسم شارةً والعنوان، في بطاقة ملونة تتعاقب درجتها (المرحلة ج)، ثم المؤلف والتاريخ بأيقونتيهما، ودائرة سهم.
--}}
@php
    use App\Support\Dates;
@endphp

<a href="{{ route('library.show', [$item->section->value, $item->slug]) }}" wire:navigate
    class="tone-card group relative flex h-full flex-col overflow-hidden">
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
        <span class="tone-card-media flex aspect-[16/10] w-full items-center justify-center" aria-hidden="true">
            {{ \App\Support\Icon::svg($item->section->icon(), 'size-14') }}
        </span>
    @endif
    <span class="flex flex-1 flex-col p-5">
        <span class="tone-chip">{{ $item->section->getLabel() }}</span>
        <span class="mt-1.5 text-lg leading-snug font-black">{{ $item->title }}</span>
        @if ($item->isReadOnly())
            <span class="tone-chip mt-2">
                {{ svg('heroicon-o-book-open', 'size-4 shrink-0', ['aria-hidden' => 'true']) }}{{ __('library.item.read_only_short') }}
            </span>
        @endif
        @if (filled($item->author) || $item->published_on)
            <span class="tone-card-muted mt-3 flex flex-wrap gap-x-4 gap-y-1 text-sm">
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
            <span class="tone-pill size-9 p-0!">
                {{ \App\Support\Icon::svg('arrow-left', 'size-4') }}
            </span>
        </span>
    </span>
</a>
