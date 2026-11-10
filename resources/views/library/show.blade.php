{{--
    صفحة العنصر: المشغّل المدمج للمرئية (يوتيوب من youtube-nocookie.com، أو ملف من نطاق معتمد)، أو
    مشغّل الصوت، أو عارض PDF على الحاسوب مع الفتح والتنزيل، أو قارئ الكتاب «للقراءة فقط» بلا تنزيل. ثم بياناته ووصفه وتصنيفاته، ثم عناصر
    ذات صلة من قسمه. (تُخزَّن نصاً — LibraryController)
--}}
@php
    use App\Support\Dates;

    $route = [$item->section->value, $item->slug];
    // «قراءاتي»: بلاغات التقدّم لمسجّل الدخول (resources/js/library-progress.js). والرابط واحد للجميع، فتُخزَّن الصفحة نصاً كما كانت
    $progress = route('library.progress', $route);
    $youtube = $item->section->takesVideoUrl() ? $item->youtubeId() : null;

    $size = \App\Support\FileSize::display($item->file_size);
@endphp

<x-page-hero :title="$item->title" :summary="$item->section->getLabel()" />

<section class="py-10 sm:py-14">
    <div class="container-page">
        {{-- المرئية والصوتية برابط يوتيوب (ملاحظات المدير التنفيذي، 25 أكتوبر 2026)، والصوتية القديمة بملفها في مشغّل الصوت أدناه --}}
        @if ($youtube || ($item->section->takesVideoUrl() && ! $item->section->keepsLegacyFile() && filled($item->video_url)))
            <div class="mb-8 overflow-hidden rounded-2xl border border-line/70 bg-ink shadow-xs">
                @if ($youtube)
                    {{-- لا يُحمَّل مشغّل يوتيوب حتى يضغط الزائر (نظام حماية البيانات الشخصية)، وصورته المصغرة من القرص العام
                         (FetchYoutubeThumbnail). يبنيه resources/js/app.js، وبلا JavaScript يفتح الرابط المقطع في يوتيوب --}}
                    <a href="https://www.youtube.com/watch?v={{ $youtube }}" data-progress="{{ $progress }}" data-video-embed="https://www.youtube-nocookie.com/embed/{{ $youtube }}?autoplay=1"
                        data-video-title="{{ __('library.item.player', ['title' => $item->title]) }}"
                        class="group relative flex aspect-video w-full flex-col items-center justify-center gap-3 overflow-hidden bg-primary p-6 text-center text-white">
                        @if ($thumbnail = $item->thumbnailUrl())
                            <img src="{{ $thumbnail }}" alt="" class="absolute inset-0 size-full object-cover opacity-60">
                        @endif
                        <span class="absolute inset-0 bg-primary/40" aria-hidden="true"></span>
                        <span class="relative inline-flex size-20 items-center justify-center rounded-full bg-white/20 backdrop-blur transition group-hover:bg-white/30" aria-hidden="true">
                            {{ \App\Support\Icon::solid('play', 'size-10') }}
                        </span>
                        <span class="relative text-lg font-semibold">{{ __('library.item.play') }}</span>
                        <span class="relative text-sm text-white/85">{{ __('library.item.youtube_notice') }}</span>
                    </a>
                @else
                    <video src="{{ $item->video_url }}" data-library-media data-progress="{{ $progress }}" controls preload="metadata" class="block aspect-video w-full">{{ __('library.item.no_video') }}</video>
                @endif
            </div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-6 card-lift p-6">
            <dl class="grid gap-x-10 gap-y-3 text-sm sm:grid-cols-3">
                <div>
                    <dt class="text-muted">{{ __('library.fields.section') }}</dt>
                    <dd class="mt-1 font-semibold text-ink">{{ $item->section->getLabel() }}</dd>
                </div>
                @if (filled($item->author))
                    <div>
                        <dt class="text-muted">{{ __('library.fields.author') }}</dt>
                        <dd class="mt-1 font-semibold text-ink">{{ $item->author }}</dd>
                    </div>
                @endif
                @if ($item->published_on)
                    <div>
                        <dt class="text-muted">{{ __('library.fields.published_on') }}</dt>
                        <dd class="mt-1 font-semibold text-ink">{{ Dates::display($item->published_on) }}</dd>
                    </div>
                @endif
            </dl>

            @if ($item->isReadOnly())
                {{-- «للقراءة فقط — بلا تحميل»: لا زر تنزيل ولا فتح للملف، والقراءة في القارئ أدناه --}}
                <p class="inline-flex items-center gap-2 rounded-full bg-primary-soft px-4 py-2 text-sm font-semibold text-primary-dark">
                    {{ svg('heroicon-o-book-open', 'size-5 shrink-0', ['aria-hidden' => 'true']) }}{{ __('library.item.read_only') }}
                </p>
            @elseif (filled($item->file))
                <div class="flex flex-wrap gap-3">
                    <x-button :href="route('library.download', $route)">
                        {{ __('library.item.download') }}
                        {{-- بلا شفافية، لتباين 4.5:1 فأكثر على الزر (governance/show) --}}
                        @if ($size)
                            <span class="font-normal">({{ $size }})</span>
                        @endif
                    </x-button>
                    @if ($item->section->takesPdf())
                        <x-button variant="outline" :href="route('library.file', $route)" target="_blank" rel="noopener">
                            {{ __('library.item.open') }}
                            <span class="sr-only">({{ __('site.opens_in_new_tab') }})</span>
                        </x-button>
                    @endif
                </div>
            @endif
        </div>

        @if (filled($item->file) && ! $item->section->takesPdf())
            <audio src="{{ route('library.file', $route) }}" data-library-media data-progress="{{ $progress }}" controls preload="none" class="mt-6 w-full"
                aria-label="{{ __('library.item.player', ['title' => $item->title]) }}">{{ __('library.item.no_audio') }}</audio>
        @endif

        @if (filled($item->description))
            <p class="mt-6 max-w-4xl text-lg leading-8 whitespace-pre-line text-ink/85">{{ $item->description }}</p>
        @endif

        @if ($item->tagList() !== [])
            <ul class="mt-6 flex flex-wrap gap-2" aria-label="{{ __('library.fields.tags') }}">
                @foreach ($item->tagList() as $tag)
                    <li>
                        <a href="{{ route('library.section', [$item->section->value, 'tag' => $tag]) }}" wire:navigate
                            class="inline-flex rounded-full border border-line bg-white px-3 py-1 text-sm text-ink transition hover:border-accent">{{ $tag }}</a>
                    </li>
                @endforeach
            </ul>
        @endif

        {{-- كتاب «للقراءة فقط»: قارئ يرسم الصفحات في <canvas> على كل العروض، بلا شريط عارض المتصفح (resources/js/pdf-reader.js) --}}
        @if (filled($item->file) && $item->isReadOnly())
            @include('library.reader', ['url' => route('library.file', $route), 'ticketUrl' => route('library.reader-ticket', $route), 'title' => $item->title, 'progress' => $progress])
        {{-- متصفحات الجوال لا تعرض PDF داخل الصفحة، فعليها زرا الفتح والتنزيل وحدهما --}}
        @elseif (filled($item->file) && $item->section->takesPdf())
            <iframe src="{{ route('library.file', $route) }}" data-progress-open="{{ $progress }}" title="{{ __('library.item.viewer', ['title' => $item->title]) }}"
                loading="lazy" class="mt-8 hidden h-[80vh] w-full lg:block concept-panel"></iframe>
            <p class="mt-6 text-sm text-muted lg:hidden">{{ __('library.item.mobile_hint') }}</p>
        @endif
    </div>
</section>

@if ($related->isNotEmpty())
    <section class="band-sky py-12 sm:py-16" aria-labelledby="related">
        <div class="container-page">
            <x-section-heading id="related">{{ __('library.item.related') }}</x-section-heading>
            <ul class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3" data-reveal-group>
                @foreach ($related as $other)
                    <li>@include('library.card', ['item' => $other])</li>
                @endforeach
            </ul>
        </div>
    </section>
@endif
