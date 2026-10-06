@props([
    'variant' => 'primary',
    'href' => null,
    'type' => 'submit',
    'size' => 'md',
])

@php
    $base = 'inline-flex items-center justify-center gap-2 rounded-lg font-semibold transition focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-not-allowed disabled:opacity-60';

    $sizes = [
        'sm' => 'px-3 py-1.5 text-sm',
        'md' => 'px-5 py-2.5 text-base',
        'lg' => 'px-6 py-3 text-lg',
    ];

    $variants = [
        'primary' => 'bg-primary text-white hover:bg-primary-dark',
        'gold' => 'bg-gold text-ink hover:bg-gold-dark hover:text-white',
        'secondary' => 'border border-line bg-white text-primary hover:border-primary',
        'danger' => 'bg-red-700 text-white hover:bg-red-800',
        'link' => 'px-0 py-0 text-primary underline decoration-gold underline-offset-4 hover:text-primary-dark',
    ];

    $classes = $base.' '.($variant === 'link' ? '' : $sizes[$size]).' '.$variants[$variant];
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif
