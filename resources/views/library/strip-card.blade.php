{{--
    بطاقة عنصر في شريط المكتبة المتحرك (Final-Library): بطاقة ملونة تتعاقب درجتها، فيها الصورة (صورة المقطع مرقّمة بزر يوتيوب، أو
    غلاف الكتاب أو البحث، أو أيقونة القسم للصوتية وما بلا صورة)، ثم العنوان سطرين. والضغط إلى صفحة العنصر، فلا تشغيل في الشريط.
    $number: رقم المقطع في المكتبة المرئية (x-video-number)، وإلا null.
--}}
@php
    $book = $item->section->takesPdf();
    $thumbnail = $item->thumbnailUrl();
@endphp

<a href="{{ route('library.show', [$item->section->value, $item->slug]) }}" wire:navigate data-card @class(['tone-card media-tile group', 'media-tile-narrow' => $book])>
    <span @class(['media-tile-thumb', 'media-tile-book' => $book, 'media-tile-audio' => $thumbnail === null])>
        @if ($thumbnail)
            <img src="{{ $thumbnail }}" alt="" loading="lazy" referrerpolicy="no-referrer" width="{{ $book ? 144 : 200 }}" height="{{ $book ? 192 : 118 }}"
                class="size-full object-cover transition duration-500 group-hover:scale-105">
        @else
            {{ \App\Support\Icon::svg($item->section->icon(), 'size-12', ['aria-hidden' => 'true']) }}
        @endif
        @if (($number ?? null) !== null)
            <x-video-number :number="$number" />
        @endif
        @if ($item->youtubeId())
            <span class="absolute inset-0 flex items-center justify-center" aria-hidden="true">
                <span class="youtube-play">{{ \App\Support\Icon::solid('play', 'size-4') }}</span>
            </span>
        @endif
    </span>
    <span class="media-tile-caption">{{ $item->title }}</span>
</a>
