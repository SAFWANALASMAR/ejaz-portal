{{--
    صفحة الألبوم: رأس بغلافه وتاريخه، ثم الوصف، ثم معرض الصور بعارضه المكبَّر.
    (تُخزَّن نصاً — AlbumController)
--}}
@php
    $photos = $album->photoList();
@endphp

<x-page-hero :title="$album->title" :summary="\App\Support\Dates::display($album->published_on)"
    :image="$album->cover() ? Storage::disk('public')->url($album->cover()) : null" />

<section class="py-10 sm:py-14">
    <div class="container-page">
        @if (filled($album->description))
            <p class="mb-8 max-w-4xl text-lg leading-8 whitespace-pre-line text-ink/85">{{ $album->description }}</p>
        @endif

        @if ($photos === [])
            <p class="text-muted">{{ __('albums.album.empty') }}</p>
        @else
            <x-gallery :images="collect($photos)->values()->map(fn (string $path, int $i): array => [
                'src' => Storage::disk('public')->url($path),
                'alt' => __('albums.album.photo_alt', ['number' => $i + 1, 'title' => $album->title]),
            ])->all()" />
        @endif
    </div>
</section>
