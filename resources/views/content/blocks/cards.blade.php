{{--
    مقطع بطاقات (التصور النهائي — Final-About «قيم الجمعية»): على الشريط السماوي، والعنوان في المنتصف بين الخطين، ثم بطاقات
    بيضاء ترتفع وتلمع، في كل بطاقة رقمها باهتاً في الزاوية، ومربع أيقونة متدرج بلونه من موضعه (أو صورة البطاقة في مربع فاتح)،
    ثم العنوان ثم الوصف.
--}}
@php
    $items = array_values(array_filter((array) ($data['items'] ?? []), fn ($item) => is_array($item) && filled($item['title'] ?? null)));
    $columns = count($items) === 4 ? 'sm:grid-cols-2 lg:grid-cols-4' : (count($items) === 5 ? 'sm:grid-cols-2 lg:grid-cols-5' : 'sm:grid-cols-2 lg:grid-cols-3');
@endphp

@if ($items !== [])
    <section class="band-sky" data-cards-block>
        <div class="mx-auto max-w-[82.5rem] px-4 pt-11 pb-13 sm:px-7">
            @if (filled($data['heading'] ?? null))
                <x-section-heading class="mb-6.5" center>{{ $data['heading'] }}</x-section-heading>
            @endif

            <ul class="grid gap-4 {{ $columns }}" data-reveal-group>
                @foreach ($items as $index => $item)
                    @php($icon = is_string($item['icon'] ?? null) ? \App\Enums\SiteIcon::tryFrom($item['icon']) : null)
                    @php($picture = is_string($item['picture'] ?? null) && $item['picture'] !== '' ? $item['picture'] : null)
                    <li class="card-lift group relative flex flex-col gap-2.5 px-5.5 py-6" data-card-item>
                        <span class="absolute end-4.5 top-3.5 text-[2.125rem] leading-none font-black text-primary/15" aria-hidden="true">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                        @if ($icon)
                            <x-icon-tile :icon="$icon->value" :index="$index" size="lg" />
                        @elseif ($picture)
                            {{-- صورة البطاقة أيقونة فوق عنوانها، فهي زخرفية --}}
                            <span class="inline-flex size-16 items-center justify-center rounded-[1.25rem] bg-primary-soft p-2.5 transition group-hover:-translate-y-1">
                                <img src="{{ Storage::disk('public')->url($picture) }}" alt="" loading="lazy" width="48" height="48" class="size-12 object-contain">
                            </span>
                        @endif
                        <h3 class="text-[1.375rem] leading-snug font-black text-primary-dark">{{ $item['title'] }}</h3>
                        @if (filled($item['text'] ?? null))
                            <p class="text-[15px] leading-[1.7] text-muted">{{ $item['text'] }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    </section>
@endif
