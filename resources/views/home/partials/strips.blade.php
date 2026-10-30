{{--
    مكان «مستجدات الجمعية» (المدير التنفيذي، 31 أكتوبر 01:28، وقرار صفوان 01:38): شريط متحرك بمرئيات يوتيوب من المكتبة بصورها التي
    جلبها الخادم، وشريط بأغلفة كتب المكتبة، وبطاقة «تابعنا على X» من حساب إكس في «الحسابات الرسمية» بلا سكربت من إكس.
    الشريطان نسختان متتاليتان (.media-strip — concept.css)، والثانية مخفية عن قارئ الشاشة ولوحة المفاتيح (inert). ويتوقفان بالمرور
    والتركيز وبزر الإيقاف (data-ticker-toggle — home.js)، وخارج الشاشة، ولا يتحركان لمن طلب تقليل الحركة. ولا تشغيل في الرئيسية:
    الضغط إلى صفحة المقطع أو الكتاب. وما فرغ يختفي.

    $videos وَ$books: Collection<LibraryItem>، و$x: array{url, handle}|null.
--}}
@if ($videos->isNotEmpty() || $books->isNotEmpty() || $x !== null)
    <section class="home-band" data-band="4" aria-label="{{ __('ui.strips.videos') }} — {{ __('ui.strips.books') }}" data-home-strips>
        <div class="mx-auto flex max-w-[82.5rem] flex-col gap-9 px-4 pt-12 pb-14 sm:px-7">
            @foreach (['videos' => $videos, 'books' => $books] as $kind => $items)
                @if ($items->isNotEmpty())
                    <div class="flex flex-col gap-4">
                        <div class="flex items-center justify-between gap-4">
                            <h2 id="home-strip-{{ $kind }}" class="flex items-center gap-2.5 text-[1.75rem] font-black text-primary-dark">
                                <x-icon-tile :icon="$kind === 'videos' ? 'play-circle' : 'book-open'" size="sm" :index="$kind === 'videos' ? 0 : 1" />
                                {{ __('ui.strips.'.$kind) }}
                            </h2>
                            <span class="flex items-center gap-3">
                                <button type="button" data-ticker-toggle="home-strip-{{ $kind }}-track" aria-pressed="false"
                                    data-label-pause="{{ __('home.ticker.pause') }}" data-label-play="{{ __('home.ticker.play') }}" aria-label="{{ __('home.ticker.pause') }}"
                                    class="inline-flex rounded-full border border-[var(--concept-border)] bg-white p-2 text-primary hover:text-primary-dark">
                                    {{ \App\Support\Icon::svg('pause', 'size-5', ['aria-hidden' => 'true', 'data-icon-pause' => true]) }}
                                    {{ \App\Support\Icon::svg('play', 'size-5', ['aria-hidden' => 'true', 'data-icon-play' => true, 'hidden' => true]) }}
                                </button>
                                <a href="{{ route('library.section', $kind === 'videos' ? \App\Enums\LibrarySection::Videos->value : \App\Enums\LibrarySection::Books->value) }}" wire:navigate class="more-link">{{ __('ui.strips.all_'.$kind) }}</a>
                            </span>
                        </div>

                        <div id="home-strip-{{ $kind }}-track" class="media-strip" data-loop data-media-strip="{{ $kind }}" style="--strip-duration: {{ max(30, $items->count() * 7) }}s">
                            <div class="media-strip-track py-2">
                                @foreach ([false, true] as $copy)
                                    <ul class="flex gap-4" @if ($copy) data-strip-copy aria-hidden="true" inert @endif>
                                        @foreach ($items as $item)
                                            <li class="shrink-0">
                                                <a href="{{ route('library.show', [$item->section->value, $item->slug]) }}" wire:navigate
                                                    @class(['card-lift group block overflow-hidden', 'w-64' => $kind === 'videos', 'w-36' => $kind === 'books'])>
                                                    <span @class(['relative block overflow-hidden bg-primary-soft', 'aspect-video' => $kind === 'videos', 'aspect-[3/4]' => $kind === 'books'])>
                                                        <img src="{{ $item->thumbnailUrl() }}" alt="" loading="lazy" width="{{ $kind === 'videos' ? 256 : 144 }}" height="{{ $kind === 'videos' ? 144 : 192 }}"
                                                            class="size-full object-cover transition duration-500 group-hover:scale-105">
                                                        @if ($kind === 'videos')
                                                            <span class="absolute inset-0 flex items-center justify-center" aria-hidden="true">
                                                                <span class="inline-flex size-11 items-center justify-center rounded-full bg-white/95 text-primary-dark">{{ \App\Support\Icon::solid('play', 'size-5') }}</span>
                                                            </span>
                                                        @endif
                                                    </span>
                                                    <span class="line-clamp-2 block px-3 py-2.5 text-sm font-extrabold leading-6 text-primary-dark">{{ $item->title }}</span>
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif
            @endforeach

            @if ($x !== null)
                <div class="identity-card flex-row! flex-wrap items-center justify-between gap-5 pb-6!" data-tone="blue" data-x-card>
                    <span class="flex items-center gap-4">
                        <span class="inline-flex size-14 shrink-0 items-center justify-center rounded-2xl bg-white text-primary-deep" aria-hidden="true">
                            {{-- علامة إكس بلون واحد، مضمّنة بلا ملف خارجي --}}
                            <svg viewBox="0 0 24 24" class="size-7" fill="currentColor" aria-hidden="true"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z" /></svg>
                        </span>
                        <span class="flex flex-col">
                            <span class="text-xl font-black text-white">{{ __('ui.strips.x_title') }}</span>
                            @if ($x['handle'] !== null)
                                <bdi dir="ltr" class="text-base text-white/85">{{ $x['handle'] }}</bdi>
                            @endif
                        </span>
                    </span>
                    <a href="{{ $x['url'] }}" target="_blank" rel="noopener noreferrer" data-x-follow
                        class="inline-flex shrink-0 items-center gap-2 rounded-full bg-white px-5 py-2.5 font-extrabold text-primary-dark transition hover:bg-[var(--concept-sky)]">
                        {{ __('ui.strips.x_follow') }}
                        <span class="sr-only">({{ __('site.opens_in_new_tab') }})</span>
                    </a>
                </div>
            @endif
        </div>
    </section>
@endif
