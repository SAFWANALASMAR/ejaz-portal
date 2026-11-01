{{--
    مقطع بطاقات (التصور النهائي — Final-About «قيم الجمعية»): على الشريط السماوي، والعنوان في المنتصف بين الخطين، ثم البطاقات
    شريطاً متحركاً (البند R16: «المتكرر يصير شريطاً متحركاً»، ومنه القيم) ببطاقات ملونة تتعاقب درجتها، في كل بطاقة رقمها باهتاً في
    الزاوية، ومربع الأيقونة (أو صورة البطاقة في مربع فاتح)، ثم العنوان ثم الوصف. والشريط x-marquee: يتوقف بالمرور والتركيز وزر
    الإيقاف، ولمن طلب تقليل الحركة صف يُمرَّر باليد.
--}}
@php
    $items = array_values(array_filter((array) ($data['items'] ?? []), fn ($item) => is_array($item) && filled($item['title'] ?? null)));
    $marqueeId = 'cards-'.substr(md5(json_encode($items) ?: ''), 0, 8);
@endphp

@if ($items !== [])
    <section class="band-sky scroll-mt-48" data-cards-block @if ($anchor = \App\Support\ContentBlocks::anchor($data['heading'] ?? null)) id="{{ $anchor }}" @endif>
        <div class="mx-auto max-w-[82.5rem] px-4 pt-9 pb-10 sm:px-7">
            @if (filled($data['heading'] ?? null))
                <x-section-heading class="mb-5" center>{{ $data['heading'] }}</x-section-heading>
            @endif

            <x-marquee :id="$marqueeId" :label="filled($data['heading'] ?? null) ? $data['heading'] : __('ui.inner.cards')" :duration="max(30, count($items) * 9)">
                @foreach ($items as $index => $item)
                    @php($icon = is_string($item['icon'] ?? null) ? \App\Enums\SiteIcon::tryFrom($item['icon']) : null)
                    @php($picture = is_string($item['picture'] ?? null) && $item['picture'] !== '' ? $item['picture'] : null)
                    <article class="tone-card group relative flex w-[17.5rem] flex-none flex-col gap-2.5 px-5.5 py-5.5" data-card-item>
                        <span class="absolute end-4.5 top-3.5 text-[2.125rem] leading-none font-black opacity-25" aria-hidden="true">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                        @if ($icon)
                            <x-icon-tile :icon="$icon->value" size="lg" />
                        @elseif ($picture)
                            {{-- صورة البطاقة أيقونة فوق عنوانها، فهي زخرفية --}}
                            <span class="inline-flex size-16 items-center justify-center rounded-[1.25rem] bg-white/85 p-2.5 transition group-hover:-translate-y-1">
                                <img src="{{ Storage::disk('public')->url($picture) }}" alt="" loading="lazy" width="48" height="48" class="size-12 object-contain">
                            </span>
                        @endif
                        <h3 class="text-[1.375rem] leading-snug font-black">{{ $item['title'] }}</h3>
                        @if (filled($item['text'] ?? null))
                            <p class="tone-card-muted text-[15px] leading-[1.7]">{{ $item['text'] }}</p>
                        @endif
                    </article>
                @endforeach
            </x-marquee>
        </div>
    </section>
@endif
