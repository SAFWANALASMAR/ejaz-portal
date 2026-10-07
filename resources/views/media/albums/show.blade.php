{{--
    صفحة الألبوم: رأس بغلافه وتاريخه، ثم الوصف، ثم شبكة الصور، وكل صورة رابط لحجمها الكامل.
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
            <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($photos as $path)
                    <li>
                        <a href="{{ Storage::disk('public')->url($path) }}" class="block overflow-hidden rounded-2xl focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                            <img src="{{ Storage::disk('public')->url($path) }}" loading="lazy" class="aspect-square w-full object-cover transition hover:scale-105"
                                alt="{{ __('albums.album.photo_alt', ['number' => $loop->iteration, 'title' => $album->title]) }}">
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</section>
