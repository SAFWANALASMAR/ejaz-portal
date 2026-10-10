@props(['type' => 'info'])

@php
    $styles = [
        'success' => 'border-emerald-200 bg-emerald-50 text-emerald-900',
        'error' => 'border-red-200 bg-red-50 text-red-900',
        'warning' => 'border-amber-200 bg-amber-50 text-amber-900',
        'info' => 'border-line bg-surface text-ink',
    ];
@endphp

<div role="{{ $type === 'error' ? 'alert' : 'status' }}" {{ $attributes->merge(['class' => 'rounded-lg border px-4 py-3 '.$styles[$type]]) }}>
    {{ $slot }}
</div>
