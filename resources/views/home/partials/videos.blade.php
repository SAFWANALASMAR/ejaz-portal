{{--
    نماذج من مرئياتنا (ملاحظة المدير التنفيذي): عناصر المكتبة المرئية المختارة للرئيسية، برابط يوتيوب.
    المشغّل لا يُحمَّل إلا بضغط الزائر ومن youtube-nocookie.com (نمط صفحة العنصر — resources/js/app.js).
--}}
<section class="py-12 lg:py-16" aria-labelledby="home-videos">
    <div class="container-page">
        <x-section-header id="home-videos" :title="__('home.videos.title')"
            :href="route('library.section', \App\Enums\LibrarySection::Videos->value)" :link-label="__('home.videos.all')" />

        <ul class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($videos as $item)
                @php($youtube = $item->youtubeId())
                <li class="overflow-hidden rounded-2xl border border-line/70 bg-white shadow-sm">
                    <div class="aspect-video">
                        <a href="https://www.youtube.com/watch?v={{ $youtube }}" data-video-embed="https://www.youtube-nocookie.com/embed/{{ $youtube }}?autoplay=1"
                            data-video-title="{{ __('library.item.player', ['title' => $item->title]) }}"
                            class="group flex size-full items-center justify-center bg-primary-dark text-white"
                            aria-label="{{ __('home.videos.play', ['title' => $item->title]) }}">
                            <span class="inline-flex size-16 items-center justify-center rounded-full bg-white/15 ring-1 ring-white/30 transition group-hover:scale-110 group-hover:bg-accent" aria-hidden="true">
                                {{ svg('heroicon-s-play', 'size-8') }}
                            </span>
                        </a>
                    </div>
                    <h3 class="p-4 font-bold text-ink">
                        <a href="{{ route('library.show', [$item->section->value, $item->slug]) }}" wire:navigate class="hover:text-primary hover:underline">{{ $item->title }}</a>
                    </h3>
                </li>
            @endforeach
        </ul>
    </div>
</section>
