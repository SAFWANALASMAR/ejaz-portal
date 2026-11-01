{{--
    معرض الشواهد برابطه السري (EvidenceGalleryController): رأس بالعنوان والبرنامج والفترة، ثم الوصف، ثم الصور بالعارض
    المكبَّر نفسه في مكتبة الصور، ثم الملفات بطاقاتٍ للتنزيل. لا يُفهرس، ولا يُرسل الرابط في Referer، ولا يُكتب في
    canonical وog:url (فيهما الرمز).
    $images: [['src', 'alt']]، و$files: [['name', 'url', 'size', 'title']] — روابط موقّعة قصيرة من الخادم.
--}}
<x-layouts.app :title="$gallery->title" noindex :canonical="false">
    @push('head')
        <meta name="referrer" content="no-referrer">
    @endpush

    <x-page-hero :title="$gallery->title" :summary="$summary" />

    <section class="py-10 sm:py-14">
        <div class="container-page space-y-12">
            <p class="inline-flex items-center gap-2 rounded-full bg-primary-soft px-4 py-2 text-sm font-bold text-primary">
                {{ svg('heroicon-o-lock-closed', 'size-5') }}
                <span>{{ __('evidence.page.private') }}</span>
            </p>

            @if (filled($gallery->description))
                <p class="max-w-4xl text-lg leading-8 whitespace-pre-line text-ink/85">{{ $gallery->description }}</p>
            @endif

            @if ($images === [] && $files === [])
                <p class="text-muted">{{ __('evidence.page.empty') }}</p>
            @endif

            @if ($images !== [])
                <div>
                    <x-section-heading class="mb-6">{{ __('evidence.page.photos') }}</x-section-heading>
                    <x-gallery :images="$images" />
                </div>
            @endif

            @if ($files !== [])
                <div>
                    <x-section-heading class="mb-6">{{ __('evidence.page.files') }}</x-section-heading>
                    <ul class="grid gap-4 lg:grid-cols-2">
                        @foreach ($files as $file)
                            <li>
                                <article class="file-card">
                                    <span class="flex size-14 shrink-0 flex-col items-center justify-center rounded-xl bg-accent text-white" aria-hidden="true">
                                        {{ svg('heroicon-o-document-text', 'size-6') }}
                                        <span class="text-[10px] leading-none font-bold tracking-wide">PDF</span>
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <h3 class="font-bold break-words text-ink">{{ $file['name'] }}</h3>
                                        <p class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-sm text-muted">
                                            <span>{{ $file['title'] }}</span>
                                            @if ($file['size'])
                                                <span>{{ \App\Support\FileSize::display($file['size']) }}</span>
                                            @endif
                                        </p>
                                    </div>
                                    <a href="{{ $file['url'] }}" rel="noreferrer" class="inline-flex shrink-0 items-center gap-1 rounded-full bg-primary px-3 py-1.5 text-sm font-bold text-white hover:bg-primary-dark">
                                        {{ svg('heroicon-o-arrow-down-tray', 'size-4') }}<span>{{ __('site.file.download') }}<span class="sr-only">: {{ $file['name'] }}</span></span>
                                    </a>
                                </article>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </section>
</x-layouts.app>
