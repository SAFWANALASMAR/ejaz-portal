{{--
    بعرض الصفحة بعد الإعلانات والأخبار (أمر التصور النهائي، النسخة 63 — البندان H6 وR7): شريط «من قناة الجمعية على يوتيوب» بمقاطع
    المكتبة المرقّمة (x-video-number) وصورها التي جلبها الخادم، ثم شريط «من مكتبة إعجاز: الكتب والأبحاث» بالأغلفة يتحرك بالاتجاه
    المعاكس، ثم بطاقة «تابعنا على X» من حساب إكس في «الحسابات الرسمية» بلا تضمين ولا سكربت من إكس، وتختفي بلا رابط.
    الشريطان x-marquee: نسختان، والثانية مخفية عن قارئ الشاشة ولوحة المفاتيح، ويتوقفان بالمرور والتركيز وزر الإيقاف وخارج الشاشة
    ولمن طلب تقليل الحركة. ولا تشغيل في الرئيسية: الضغط إلى صفحة المقطع أو الكتاب. وما فرغ يختفي، والعناوين من «محتوى الرئيسية».

    $videos وَ$books: Collection<LibraryItem>، و$x: array{url, handle}|null، و$headings: عناوين الأقسام.
--}}
<section class="home-band" data-band="{{ $band }}" aria-label="{{ $headings['videos'] }} — {{ $headings['books'] }}" data-home-strips>
    <div class="mx-auto flex max-w-[82.5rem] flex-col gap-5 px-4 pt-6 pb-7 sm:px-7 lg:pt-7 lg:pb-8">
        @foreach (['videos' => $videos, 'books' => $books] as $kind => $items)
            @if ($items->isNotEmpty())
                <div class="flex flex-col gap-3">
                    <div class="flex items-center justify-between gap-4">
                        <x-section-heading id="home-strip-{{ $kind }}" :icon="$kind === 'videos' ? 'play-circle' : 'book-open'">{{ $headings[$kind] }}</x-section-heading>
                        <a href="{{ route('library.section', $kind === 'videos' ? \App\Enums\LibrarySection::Videos->value : \App\Enums\LibrarySection::Books->value) }}" wire:navigate class="more-link shrink-0">{{ __('ui.news.more') }}</a>
                    </div>

                    <x-marquee id="home-strip-{{ $kind }}-track" :label="$headings[$kind]" :duration="max(30, $items->count() * 7)" data-media-strip="{{ $kind }}"
                        @class(['media-marquee', 'media-marquee-reverse' => $kind === 'books'])>
                        @foreach ($items as $item)
                            <a href="{{ route('library.show', [$item->section->value, $item->slug]) }}" wire:navigate data-card class="tone-card media-tile group">
                                <span @class(['media-tile-thumb', 'media-tile-book' => $kind === 'books'])>
                                    <img src="{{ $item->thumbnailUrl() }}" alt="" loading="lazy" width="{{ $kind === 'videos' ? 200 : 144 }}" height="{{ $kind === 'videos' ? 118 : 192 }}"
                                        class="size-full object-cover transition duration-500 group-hover:scale-105">
                                    @if ($kind === 'videos')
                                        <x-video-number :number="$loop->iteration" />
                                        <span class="absolute inset-0 flex items-center justify-center" aria-hidden="true">
                                            <span class="youtube-play">{{ \App\Support\Icon::solid('play', 'size-4') }}</span>
                                        </span>
                                    @endif
                                </span>
                                <span class="media-tile-caption">{{ $item->title }}</span>
                            </a>
                        @endforeach
                    </x-marquee>
                </div>
            @endif
        @endforeach

        @if ($x !== null)
            <div class="x-card" data-x-card>
                <span class="inline-flex size-13 shrink-0 items-center justify-center rounded-[0.9375rem] border border-white/40 bg-black" aria-hidden="true">
                    {{-- علامة إكس بلون واحد، مضمّنة بلا ملف خارجي --}}
                    <svg viewBox="0 0 24 24" class="size-6 text-white" fill="currentColor" aria-hidden="true"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z" /></svg>
                </span>
                <span class="flex min-w-0 flex-1 flex-col">
                    <span class="text-[1.1875rem] font-black">{{ $headings['x'] }}</span>
                    @if ($x['handle'] !== null)
                        <bdi dir="ltr" class="text-sm text-white/85">{{ $x['handle'] }}</bdi>
                    @endif
                </span>
                <a href="{{ $x['url'] }}" target="_blank" rel="noopener noreferrer" data-x-follow
                    class="inline-flex shrink-0 items-center gap-1.5 rounded-full border border-white/40 bg-white/15 px-4.5 py-2 text-sm font-extrabold text-white transition hover:bg-white/25">
                    {{ $headings['x_follow'] }}
                    {{ \App\Support\Icon::svg('arrow-up-left', 'size-4', ['aria-hidden' => 'true']) }}
                    <span class="sr-only">({{ __('site.opens_in_new_tab') }})</span>
                </a>
            </div>
        @endif
    </div>
</section>
