{{--
    معرض الصور: شبكة متناسقة (الأولى أكبر على الحاسوب)، وكل صورة تفتح في عارض مكبَّر بـ <dialog>
    (التالي والسابق ولوحة المفاتيح، وEsc يغلقه). التفاعل في resources/js/gallery.js بلا مكتبة.
    $images: [['src' => رابط, 'alt' => نص بديل]] — صور القرص العام وحدها؛ والخاصة برابط مؤقت موقّع بعد فحص الصلاحية.
--}}
@props(['images' => []])

@if ($images !== [])
    <div data-gallery>
        <ul @class(['gallery-grid', 'gallery-grid-featured' => count($images) >= 5])>
            @foreach ($images as $image)
                <li>
                    <button type="button" data-gallery-open="{{ $loop->index }}"
                        class="group block size-full overflow-hidden rounded-2xl bg-surface focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
                        aria-label="{{ __('site.gallery.open', ['number' => $loop->iteration]) }}">
                        <img src="{{ $image['src'] }}" alt="{{ $image['alt'] }}" loading="lazy"
                            class="aspect-square size-full object-cover transition duration-500 group-hover:scale-105">
                    </button>
                </li>
            @endforeach
        </ul>

        <dialog data-gallery-dialog aria-label="{{ __('site.gallery.viewer') }}"
            data-counter="{{ __('site.gallery.counter', ['current' => ':current', 'total' => ':total']) }}"
            class="m-auto max-h-none max-w-none bg-transparent p-0 backdrop:bg-ink/95">
            <figure class="flex h-dvh w-screen flex-col items-center justify-center gap-3 p-4 sm:p-10">
                <img data-gallery-image src="{{ $images[0]['src'] }}" alt="{{ $images[0]['alt'] }}" class="max-h-[80dvh] max-w-full rounded-xl object-contain shadow-2xl">
                <figcaption class="flex items-center gap-4 text-sm text-white/85">
                    <span data-gallery-caption>{{ $images[0]['alt'] }}</span>
                    <span data-gallery-counter class="tabular-nums text-white/60"></span>
                </figcaption>
            </figure>

            <button type="button" data-gallery-close class="absolute end-4 top-4 rounded-full bg-white/10 p-3 text-white hover:bg-white/20" aria-label="{{ __('site.gallery.close') }}">
                {{ svg('heroicon-o-x-mark', 'size-6') }}
            </button>
            @if (count($images) > 1)
                {{-- في الاتجاه من اليمين: السابقة يميناً والتالية يساراً --}}
                <button type="button" data-gallery-prev class="absolute start-4 top-1/2 -translate-y-1/2 rounded-full bg-white/10 p-3 text-white hover:bg-white/20" aria-label="{{ __('site.gallery.previous') }}">
                    {{ svg('heroicon-o-chevron-right', 'size-7') }}
                </button>
                <button type="button" data-gallery-next class="absolute end-4 top-1/2 -translate-y-1/2 rounded-full bg-white/10 p-3 text-white hover:bg-white/20" aria-label="{{ __('site.gallery.next') }}">
                    {{ svg('heroicon-o-chevron-left', 'size-7') }}
                </button>
            @endif
        </dialog>
    </div>
@endif
