{{-- شارة حالة بلونها: حالة الطلب، أو حالة التسجيل في برنامج --}}
@php
    $colors = [
        'gray' => 'bg-gray-100 text-gray-700',
        'info' => 'bg-primary-soft text-primary',
        'warning' => 'bg-white text-primary-dark ring-1 ring-inset ring-brand-light',
        'success' => 'bg-primary text-white',
        'danger' => 'bg-red-100 text-red-900',
    ];
@endphp

<span class="inline-flex shrink-0 items-center rounded-full px-3 py-1 text-sm font-semibold {{ $colors[$status->getColor()] }}">{{ $status->getLabel() }}</span>
