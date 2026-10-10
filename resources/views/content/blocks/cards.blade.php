{{--
    مقطع بطاقات (المرجع البصري — بطاقة الميزة): أيقونة خطية في دائرة فاتحة، ثم العنوان، ثم وصف
    قصير، ثم شريط قصير ملون أسفلها. للقيم والأهداف والرسالة والرؤية.
--}}
@php
    $items = array_values(array_filter((array) ($data['items'] ?? []), fn ($item) => is_array($item) && filled($item['title'] ?? null)));
@endphp

@if ($items !== [])
    <section class="py-10 sm:py-12">
        <div class="container-page">
            @if (filled($data['heading'] ?? null))
                <x-section-heading class="mb-8">{{ $data['heading'] }}</x-section-heading>
            @endif

            <ul class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($items as $item)
                    @php($icon = is_string($item['icon'] ?? null) ? \App\Enums\SiteIcon::tryFrom($item['icon']) : null)
                    <li class="flex flex-col rounded-2xl border border-line/70 bg-white p-6 shadow-xs">
                        @if ($icon)
                            <span class="inline-flex size-12 items-center justify-center rounded-full bg-primary-soft text-primary" aria-hidden="true">
                                {{ svg('heroicon-o-'.$icon->value, 'size-6') }}
                            </span>
                        @endif
                        <h3 @class(['text-lg font-bold text-ink', 'mt-4' => $icon])>{{ $item['title'] }}</h3>
                        @if (filled($item['text'] ?? null))
                            <p class="mt-2 leading-7 text-muted">{{ $item['text'] }}</p>
                        @endif
                        <span class="mt-5 block h-1 w-10 rounded-full bg-gold" aria-hidden="true"></span>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>
@endif
