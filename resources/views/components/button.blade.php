{{-- زر موحد كبسولي الشكل (docs/design/ui-reference.md): رئيسي، أو بإطار، أو مميز (بنفسجي)، أو خطر، أو رابط نصي --}}
@props([
    'variant' => 'primary',
    'href' => null,
    'type' => 'submit',
    'size' => 'md',
    'arrow' => false,
])

@php
    $base = 'keep-color inline-flex items-center justify-center gap-2 rounded-full font-semibold transition focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-not-allowed disabled:opacity-60';

    $sizes = [
        'sm' => 'px-4 py-1.5 text-sm',
        'md' => 'px-6 py-2.5 text-base',
        'lg' => 'px-7 py-3 text-lg',
    ];

    $variants = [
        // الممتلئة بلمعة عند المرور وتموّج عند الضغط (.btn-shine — التحسين البصري)
        'primary' => 'btn-shine bg-primary text-white shadow-sm hover:bg-primary-dark',
        'outline' => 'border border-primary bg-white text-primary hover:bg-primary-soft',
        'accent' => 'btn-shine bg-accent text-white shadow-sm hover:bg-accent-dark',
        'secondary' => 'border border-line bg-white text-primary hover:border-primary',
        'danger' => 'bg-red-700 text-white hover:bg-red-800',
        'link' => 'rounded-none px-0 py-0 text-primary underline decoration-accent underline-offset-4 hover:text-primary-dark',
    ];

    $classes = $base.' '.($variant === 'link' ? '' : $sizes[$size]).' '.$variants[$variant];
@endphp

{{-- الرابط إلى صفحة داخلية بالتنقّل السريع، وما يفتح تبويباً جديداً أو يُحمَّل كاملاً بدونه (SpaLink) --}}
@if ($href)
    <a href="{{ $href }}" @navigate($attributes->has('target') ? null : $href) {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
        @if ($arrow)
            {{ \App\Support\Icon::svg('arrow-left', 'size-4 shrink-0') }}
        @endif
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
        @if ($arrow)
            {{ \App\Support\Icon::svg('arrow-left', 'size-4 shrink-0') }}
        @endif
    </button>
@endif
