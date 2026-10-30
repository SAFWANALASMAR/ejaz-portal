{{-- شارة حالة البرنامج (التصور النهائي — Final-Page): مفتوح أخضر، وقريباً بيضاء، ومكتمل ومنتهٍ رمادي فاتح --}}
@php
    $colors = [
        'success' => 'bg-secondary text-white',
        'warning' => 'bg-white text-primary-dark',
        'gray' => 'bg-[#E3E8EE] text-ink',
    ];
@endphp

<span class="inline-flex items-center rounded-full px-3 py-0.5 text-sm font-extrabold shadow-sm {{ $colors[$status->getColor()] }} {{ $class ?? '' }}">{{ $status->getLabel() }}</span>
