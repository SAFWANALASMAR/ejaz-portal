{{--
    بعرض الصفحة تحت «خدماتنا» (ملاحظة المدير ٢٤) (أمر التصور النهائي، النسخة 63 — البندان H6 وR7): شريط «من قناة الجمعية على يوتيوب» بمقاطع
    المكتبة المرقّمة (x-video-number) وصورها التي جلبها الخادم، ثم شريط «من مكتبة إعجاز: الكتب والمؤلفات» بالأغلفة
    بالاتجاه المعاكس (إضافة توجيه المدير، البند ١٧: يوتيوب من اليمين إلى اليسار، والمكتبة من اليسار إلى اليمين)، ثم بطاقة «تابعنا على X» من حساب إكس في «الحسابات الرسمية» بلا تضمين ولا سكربت من إكس، وتختفي بلا رابط.
    الشريطان x-marquee: نسختان، والثانية مخفية عن قارئ الشاشة ولوحة المفاتيح، ويتوقفان بالمرور والتركيز وزر الإيقاف وخارج الشاشة
    ولمن طلب تقليل الحركة. ولا تشغيل في الرئيسية: الضغط إلى صفحة المقطع أو الكتاب. وما فرغ يختفي، والعناوين من «محتوى الرئيسية».

    $videos وَ$books: Collection<LibraryItem>، و$x: array{url, handle}|null، و$headings: عناوين الأقسام.
    وثلاثة عناصر أو أقل ثابتة في الوسط بلا تكرار (فحص 11 نوفمبر 2026، ويلغي «الشريط القليل يتحرك» من البند ١١ في إضافة توجيه
    المدير، 3 نوفمبر). وما فوقها يتحرك: ستة مقاطع (200 بكسل وفاصل) أو ثمانية كتب (144 بكسل وفاصل) تملأ عرض المحتوى على 1440
    (نحو 1264 بكسل)، وما قلّ عنها يتكرر في المسار (repeat) حتى يملأه.
--}}
@php
    $movingFrom = ['videos' => 6, 'books' => 8];
    $staticUpTo = 3;
    // كل شريط عكس الذي قبله (إضافة توجيه المدير، 3 نوفمبر 2026، البند ١٧): يوتيوب من اليمين إلى اليسار، ثم الكتب من اليسار إلى اليمين.
    // وشريط «الأبحاث والدراسات» خرج من الرئيسية (ملاحظات المدير ليلة 5 نوفمبر 2026، البند 3): شريط واحد للمكتبة، وقسمها باقٍ فيها
    $flipped = ['videos' => true, 'books' => false];
@endphp
<section class="home-band" data-band="{{ $band }}" aria-label="{{ $headings['videos'] }} — {{ $headings['books'] }}" data-home-strips>
    <div class="mx-auto flex max-w-[82.5rem] flex-col gap-5 px-4 pt-6 pb-7 sm:px-7 lg:pt-7 lg:pb-8">
        @foreach (['videos' => $videos, 'books' => $books] as $kind => $items)
            @if ($items->isNotEmpty())
                <div class="flex flex-col gap-3">
                    <div class="flex items-center justify-between gap-4">
                        <x-section-heading id="home-strip-{{ $kind }}" :icon="$kind === 'videos' ? 'play-circle' : 'book-open'">{{ $headings[$kind] }}</x-section-heading>
                        <a href="{{ $kind === 'videos' ? route('library.section', \App\Enums\LibrarySection::Videos->value) : route('library.index') }}" wire:navigate class="more-link shrink-0">{{ __('ui.news.more') }}<span class="sr-only">: {{ $headings[$kind] }}</span></a>
                    </div>

                    {{-- ثلاثة عناصر أو أقل ثابتة في الوسط بلا تكرار ولا حركة (فحص الموقع الحي، 11 نوفمبر 2026 — كان كتابان يتكرران أربع
                         مرات)، وما فوقها شريط متحرك يتكرر حتى يملأ العرض --}}
                    @if ($items->count() <= $staticUpTo)
                        <div class="media-strip-static" data-media-strip="{{ $kind }}" data-static-strip>
                            @foreach ($items as $item)
                                @include('home.partials.media-tile', ['item' => $item, 'kind' => $kind, 'number' => $loop->iteration])
                            @endforeach
                        </div>
                    @else
                        @php($repeat = (int) ceil($movingFrom[$kind] / $items->count()))

                        <x-marquee id="home-strip-{{ $kind }}-track" :label="$headings[$kind]" :duration="max(30, $items->count() * 7) * $repeat" :repeat="$repeat" data-media-strip="{{ $kind }}"
                            @class(['media-marquee', 'media-marquee-flip' => $flipped[$kind], 'media-marquee-books' => $kind !== 'videos'])>
                            @foreach ($items as $item)
                                @include('home.partials.media-tile', ['item' => $item, 'kind' => $kind, 'number' => $loop->iteration])
                            @endforeach
                        </x-marquee>
                    @endif
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
