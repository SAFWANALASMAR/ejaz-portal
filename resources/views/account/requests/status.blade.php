{{-- شارة حالة الطلب بلونها --}}
@php
    $colors = [
        'gray' => 'bg-gray-100 text-gray-700',
        'info' => 'bg-primary-soft text-primary',
        'warning' => 'bg-amber-100 text-amber-900',
        'success' => 'bg-emerald-100 text-emerald-900',
        'danger' => 'bg-red-100 text-red-900',
    ];
@endphp

<span class="inline-flex shrink-0 items-center rounded-full px-3 py-1 text-sm font-semibold {{ $colors[$status->getColor()] }}">{{ $status->getLabel() }}</span>
