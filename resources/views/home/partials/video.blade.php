{{--
    مقطع الرئيسية من «إعدادات الموقع» (كان مضمّناً في رئيسية الموقع السابق). المشغّل لا يُحمَّل إلا
    بضغط الزائر ومن youtube-nocookie.com (نمط نماذج الفيديو — resources/js/app.js).
--}}
<section class="py-12 lg:py-16" aria-label="{{ __('home.video.label') }}">
    <div class="container-page">
        <div class="mx-auto aspect-video max-w-4xl overflow-hidden rounded-2xl shadow-sm">
            <a href="https://www.youtube.com/watch?v={{ $youtube }}" data-video-embed="https://www.youtube-nocookie.com/embed/{{ $youtube }}?autoplay=1"
                data-video-title="{{ __('home.video.label') }}"
                class="group flex size-full items-center justify-center bg-primary-dark text-white"
                aria-label="{{ __('home.video.play') }}">
                <span class="inline-flex size-20 items-center justify-center rounded-full bg-white/15 ring-1 ring-white/30 transition group-hover:scale-110 group-hover:bg-accent" aria-hidden="true">
                    {{ svg('heroicon-s-play', 'size-10') }}
                </span>
            </a>
        </div>
    </div>
</section>
