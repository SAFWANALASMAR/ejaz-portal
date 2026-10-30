{{-- مكتبة الصور: بطاقات الألبومات المنشورة، الأحدث أولاً، بترقيم (تُخزَّن الصفحة الأولى — AlbumController) --}}
<x-page-hero :title="__('albums.title')" />

<section class="py-10 sm:py-14">
    <div class="container-page">
        @if ($albums->isEmpty())
            <p class="text-muted">{{ __('albums.list.empty') }}</p>
        @else
            <ul class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3" data-reveal-group>
                @foreach ($albums as $album)
                    <li>@include('media.albums.card', ['album' => $album])</li>
                @endforeach
            </ul>

            {{ $albums->links('partials.pagination') }}
        @endif
    </div>
</section>
