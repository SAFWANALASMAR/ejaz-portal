{{-- حالة فارغة مرسومة واحدة لرئيسية اللوحة وعناصرها (طلب لوحة الإدارة، 4 نوفمبر 2026 — الدفعة ٢): نجمة ثمانية بخطوط زرقاء، وعنوان،
     وسطر شرح، وروابط كبسولية إن وُجدت. $framed للحالة القائمة بذاتها، وبدونه داخل قسم عنصر --}}
@props(['heading', 'body' => null, 'links' => [], 'framed' => false])

<div @class(['ej-empty', 'ej-empty-framed' => $framed]) data-ej-empty {{ $attributes }}>
    <svg class="ej-empty-art" viewBox="0 0 160 120" aria-hidden="true">
        <path d="M80 10l11 27 27 11-27 11-11 27-11-27-27-11 27-11z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" opacity=".35"/>
        <rect x="58" y="26" width="44" height="44" rx="6" transform="rotate(45 80 48)" fill="none" stroke="currentColor" stroke-width="2" opacity=".55"/>
        <circle cx="80" cy="48" r="9" fill="currentColor" opacity=".18"/>
        <path d="M30 104h100" stroke="currentColor" stroke-width="2" stroke-linecap="round" opacity=".3"/>
        <path d="M52 112h56" stroke="currentColor" stroke-width="2" stroke-linecap="round" opacity=".18"/>
    </svg>
    <p class="ej-empty-heading">{{ $heading }}</p>
    @if (filled($body))
        <p class="ej-empty-body">{{ $body }}</p>
    @endif
    @if ($links !== [])
        <ul class="ej-empty-links">
            @foreach ($links as $link)
                <li>
                    <a href="{{ $link['url'] }}" class="ej-empty-link" data-ej-empty-link>
                        @if ($link['icon'] ?? null)
                            {{ \Filament\Support\generate_icon_html($link['icon'], size: \Filament\Support\Enums\IconSize::Small) }}
                        @endif
                        <span>{{ $link['label'] }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</div>
