{{--
    مقطع الرئيسية من «إعدادات الموقع» (كان مضمّناً في رئيسية الموقع السابق). وقبل الضغط صورة المقطع التي جلبها الخادم مرة
    (FetchHomeVideoThumbnail، بآلية صور المكتبة نفسها) تحت تدرج داكن بزر تشغيل (ملاحظة المدير التنفيذي، 29 أكتوبر 2026)،
    وإلى أن تُجلب الخلفية الداكنة. والمشغّل لا يُحمَّل إلا بضغط الزائر ومن youtube-nocookie.com (resources/js/app.js).
--}}
<section class="home-band py-12 lg:py-16" data-band="4" aria-label="{{ __('home.video.label') }}">
    <div class="container-page">
        @if ($youtube === null)
            {{-- مُظهر من الإعدادات بلا رابط صالح: بحالته الفارغة (قرار صفوان، 11 نوفمبر 2026) --}}
            <x-empty-state icon="play-circle" :message="__('home.empty.video')" data-home-empty="video" />
        @else
        <div class="card-lift mx-auto aspect-video max-w-4xl overflow-hidden">
            <a href="https://www.youtube.com/watch?v={{ $youtube }}" data-video-embed="https://www.youtube-nocookie.com/embed/{{ $youtube }}?autoplay=1"
                data-video-title="{{ __('home.video.label') }}"
                class="group relative flex size-full items-center justify-center bg-primary text-white"
                aria-label="{{ __('home.video.play') }}">
                @if ($thumbnail)
                    <span class="skeleton absolute inset-0 block">
                        <img src="{{ $thumbnail }}" alt="" loading="lazy" width="480" height="270" data-video-thumbnail
                            class="size-full object-cover transition duration-700 group-hover:scale-105">
                    </span>
                @endif
                <span class="absolute inset-0 bg-linear-to-t from-primary/85 via-primary/25 to-transparent" aria-hidden="true"></span>
                <span class="relative inline-flex size-20 items-center justify-center rounded-full bg-white/20 ring-1 ring-white/40 backdrop-blur-sm transition group-hover:scale-110 group-hover:bg-accent" aria-hidden="true">
                    {{ \App\Support\Icon::solid('play', 'size-9') }}
                </span>
            </a>
        </div>
        @endif
    </div>
</section>
