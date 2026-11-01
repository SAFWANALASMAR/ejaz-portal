@props(['type' => 'info'])

@php
    $styles = [
        'success' => 'border-brand-light bg-pale text-primary-dark',
        'error' => 'border-red-200 bg-red-50 text-red-900',
        'warning' => 'border-brand-light bg-primary-soft text-primary-dark',
        'info' => 'border-line bg-surface text-ink',
    ];
@endphp

<div role="{{ $type === 'error' ? 'alert' : 'status' }}" {{ $attributes->merge(['class' => 'rounded-lg border px-4 py-3 '.$styles[$type]]) }}>
    {{ $slot }}
</div>
