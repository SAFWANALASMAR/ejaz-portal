{{--
    صفحة العنصر: المشغّل المدمج للمرئية (يوتيوب من youtube-nocookie.com، أو ملف من نطاق معتمد)، أو
    مشغّل الصوت، أو عارض PDF على الحاسوب مع الفتح والتنزيل. ثم بياناته ووصفه وتصنيفاته، ثم عناصر
    ذات صلة من قسمه. (تُخزَّن نصاً — LibraryController)
--}}
@php
    use App\Support\Dates;

    $route = [$item->section->value, $item->slug];
    $youtube = $item->section->takesVideoUrl() ? $item->youtubeId() : null;

    $size = \App\Support\FileSize::display($item->file_size);
@endphp

<x-page-hero :title="$item->title" :summary="$item->section->getLabel()" />

<section class="py-10 sm:py-14">
    <div class="container-page">
        @if ($item->section->takesVideoUrl())
            <div class="mb-8 overflow-hidden rounded-2xl border border-line/70 bg-ink shadow-xs">
                @if ($youtube)
                    {{-- لا يُحمَّل مشغّل يوتيوب حتى يضغط الزائر، فلا يتصل متصفحه بخوادم جوجل قبل ذلك (نظام
                         حماية البيانات الشخصية). يبنيه resources/js/app.js، وبلا JavaScript يفتح الرابط المقطع في يوتيوب --}}
                    <a href="https://www.youtube.com/watch?v={{ $youtube }}" data-video-embed="https://www.youtube-nocookie.com/embed/{{ $youtube }}?autoplay=1"
                        data-video-title="{{ __('library.item.player', ['title' => $item->title]) }}"
                        class="group flex aspect-video w-full flex-col items-center justify-center gap-3 bg-primary-dark p-6 text-center text-white">
                        <span class="inline-flex size-20 items-center justify-center rounded-full bg-white/10 transition group-hover:bg-white/20" aria-hidden="true">
                            {{ svg('heroicon-s-play', 'size-10') }}
                        </span>
                        <span class="text-lg font-semibold">{{ __('library.item.play') }}</span>
                        <span class="text-sm text-white/70">{{ __('library.item.youtube_notice') }}</span>
                    </a>
                @else
                    <video src="{{ $item->video_url }}" controls preload="metadata" class="block aspect-video w-full">{{ __('library.item.no_video') }}</video>
                @endif
            </div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-6 rounded-2xl border border-line/70 bg-white p-6 shadow-xs">
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

            @if (filled($item->file))
                <div class="flex flex-wrap gap-3">
                    <x-button :href="route('library.download', $route)">
                        {{ __('library.item.download') }}
                        @if ($size)
                            <span class="font-normal opacity-80">({{ $size }})</span>
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
            <audio src="{{ route('library.file', $route) }}" controls preload="none" class="mt-6 w-full"
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

        {{-- متصفحات الجوال لا تعرض PDF داخل الصفحة، فعليها زرا الفتح والتنزيل وحدهما --}}
        @if (filled($item->file) && $item->section->takesPdf())
            <iframe src="{{ route('library.file', $route) }}" title="{{ __('library.item.viewer', ['title' => $item->title]) }}"
                loading="lazy" class="mt-8 hidden h-[80vh] w-full rounded-2xl border border-line/70 bg-surface lg:block"></iframe>
            <p class="mt-6 text-sm text-muted lg:hidden">{{ __('library.item.mobile_hint') }}</p>
        @endif
    </div>
</section>

@if ($related->isNotEmpty())
    <section class="bg-surface py-12 sm:py-16" aria-labelledby="related">
        <div class="container-page">
            <x-section-heading id="related">{{ __('library.item.related') }}</x-section-heading>
            <ul class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($related as $other)
                    <li>@include('library.card', ['item' => $other])</li>
                @endforeach
            </ul>
        </div>
    </section>
@endif
