{{--
    نماذج من مرئياتنا (ملاحظة المدير التنفيذي): عناصر المكتبة المرئية المختارة للرئيسية، برابط يوتيوب. وقبل الضغط صورة المقطع
    الحقيقية التي جلبها الخادم وحفظها (LibraryItem::thumbnailUrl — FetchYoutubeThumbnail)، تحت تدرج داكن بزر تشغيل وعنوان
    (ملاحظة المدير، 29 أكتوبر 2026). ولعنصر بلا صورة بعد الخلفية الداكنة. المشغّل لا يُحمَّل إلا بضغط الزائر ومن
    youtube-nocookie.com (resources/js/app.js)، فلا يتصل متصفحه بجوجل قبله.
--}}
<section class="section-tint py-12 lg:py-16" aria-labelledby="home-videos">
    <div class="container-page">
        <x-section-header id="home-videos" :title="__('home.videos.title')"
            :href="route('library.section', \App\Enums\LibrarySection::Videos->value)" :link-label="__('home.videos.all')" />

        <ul class="scroll-row mt-8" data-reveal-group>
            @foreach ($videos as $item)
                @php($youtube = $item->youtubeId())
                @php($thumbnail = $item->thumbnailUrl())
                {{-- العنوان فوق الصورة رابطاً إلى صفحة المقطع، ويختفي حين يحل المشغّل مكان الصورة --}}
                <li class="card-lift relative overflow-hidden [&:has(iframe)_[data-video-caption]]:hidden">
                    <a href="https://www.youtube.com/watch?v={{ $youtube }}" data-video-embed="https://www.youtube-nocookie.com/embed/{{ $youtube }}?autoplay=1"
                        data-video-title="{{ __('library.item.player', ['title' => $item->title]) }}"
                        class="group relative block aspect-video overflow-hidden bg-primary text-white"
                        aria-label="{{ __('home.videos.play', ['title' => $item->title]) }}">
                        @if ($thumbnail)
                            <span class="skeleton absolute inset-0 block">
                                <img src="{{ $thumbnail }}" alt="" loading="lazy" data-video-thumbnail
                                    class="size-full object-cover transition duration-700 group-hover:scale-105">
                            </span>
                        @endif
                        <span class="absolute inset-0 bg-linear-to-t from-primary/90 via-primary/30 to-transparent" aria-hidden="true"></span>
                        <x-video-number :number="$loop->iteration" />
                        <span class="absolute inset-0 flex items-center justify-center" aria-hidden="true">
                            <span class="inline-flex size-16 items-center justify-center rounded-full bg-white/20 ring-1 ring-white/40 backdrop-blur-sm transition group-hover:scale-110 group-hover:bg-accent">
                                {{ \App\Support\Icon::solid('play', 'size-7') }}
                            </span>
                        </span>
                    </a>
                    <h3 data-video-caption class="pointer-events-none absolute inset-x-0 bottom-0 p-4 font-bold leading-7 text-white">
                        <a href="{{ route('library.show', [$item->section->value, $item->slug]) }}" wire:navigate class="pointer-events-auto line-clamp-2 hover:underline">{{ $item->title }}</a>
                    </h3>
                </li>
            @endforeach
        </ul>
    </div>
</section>
