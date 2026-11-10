{{--
    بطاقة مقطع أو كتاب في شريطي الرئيسية (home.partials.strips)، متحركاً أو ثابتاً. $item: LibraryItem، و$kind: videos أو books،
    و$number: ترتيب المقطع (x-video-number).
--}}
<a href="{{ route('library.show', [$item->section->value, $item->slug]) }}" wire:navigate data-card class="tone-card media-tile group">
    <span @class(['media-tile-thumb', 'media-tile-book' => $kind !== 'videos'])>
        @if ($thumbnail = $item->thumbnailUrl())
            <img src="{{ $thumbnail }}" alt="" loading="lazy" width="{{ $kind === 'videos' ? 200 : 144 }}" height="{{ $kind === 'videos' ? 118 : 192 }}"
                class="size-full object-cover transition duration-500 group-hover:scale-105">
        @else
            {{-- بلا صورة: مربع بتدرج أزرق وأيقونة، كما في التصور (البند ٢٤). والكتاب والبحث بأيقونة الكتاب نفسها
                 (إضافة توجيه المدير، 3 نوفمبر 2026، البند ٦) --}}
            <span class="media-tile-placeholder" data-thumb-placeholder aria-hidden="true">
                @if ($kind !== 'videos')
                    {{ \App\Support\Icon::svg(\App\Enums\LibrarySection::Books->icon(), 'size-10') }}
                @endif
            </span>
        @endif
        @if ($kind === 'videos')
            <x-video-number :number="$number" />
            <span class="absolute inset-0 flex items-center justify-center" aria-hidden="true">
                <span class="youtube-play">{{ \App\Support\Icon::solid('play', 'size-4') }}</span>
            </span>
        @endif
    </span>
    <span class="media-tile-caption">{{ $item->title }}</span>
</a>
