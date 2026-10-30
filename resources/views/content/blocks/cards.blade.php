{{--
    مقطع بطاقات (القيم والأهداف — درس الموقع القديم): أيقونة كبيرة في الوسط في مربع متدرج (x-icon-tile)، أو صورة البطاقة في
    مربع فاتح، ثم العنوان تحتها ثم الوصف. بطاقات ترتفع بحد متوهج، وتظهر تباعاً عند التمرير.
--}}
@php
    $items = array_values(array_filter((array) ($data['items'] ?? []), fn ($item) => is_array($item) && filled($item['title'] ?? null)));
    $tones = ['blue', 'violet', 'green', 'navy', 'sky'];
    $columns = count($items) === 4 ? 'sm:grid-cols-2 lg:grid-cols-4' : (count($items) === 5 ? 'sm:grid-cols-2 lg:grid-cols-5' : 'sm:grid-cols-2 lg:grid-cols-3');
@endphp

@if ($items !== [])
    <section class="py-12 sm:py-16" data-cards-block>
        <div class="container-page">
            @if (filled($data['heading'] ?? null))
                <x-section-heading class="mb-10" center>{{ $data['heading'] }}</x-section-heading>
            @endif

            <ul class="grid gap-5 {{ $columns }}" data-reveal-group>
                @foreach ($items as $index => $item)
                    @php($icon = is_string($item['icon'] ?? null) ? \App\Enums\SiteIcon::tryFrom($item['icon']) : null)
                    @php($picture = is_string($item['picture'] ?? null) && $item['picture'] !== '' ? $item['picture'] : null)
                    <li class="card-glow group flex flex-col items-center p-6 text-center" data-card-item>
                        @if ($icon)
                            <x-icon-tile :icon="$icon->value" :tone="$tones[$index % count($tones)]" size="xl" />
                        @elseif ($picture)
                            {{-- صورة البطاقة أيقونة فوق عنوانها، فهي زخرفية --}}
                            <span class="inline-flex size-20 items-center justify-center rounded-3xl bg-primary-soft p-3 transition group-hover:-translate-y-1">
                                <img src="{{ Storage::disk('public')->url($picture) }}" alt="" loading="lazy" width="56" height="56" class="size-14 object-contain">
                            </span>
                        @endif
                        <h3 @class(['text-lg leading-8 font-bold text-primary-dark', 'mt-5' => $icon || $picture])>{{ $item['title'] }}</h3>
                        @if (filled($item['text'] ?? null))
                            <p class="mt-2 leading-7 text-muted">{{ $item['text'] }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    </section>
@endif
