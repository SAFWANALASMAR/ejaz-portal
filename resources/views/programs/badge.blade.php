{{-- شارة حالة البرنامج (الصورة 06): مفتوح أخضر، ومكتمل ومنتهٍ رمادي، وقريباً ذهبي --}}
@php
    $colors = [
        'success' => 'bg-primary text-white',
        'warning' => 'bg-accent text-white',
        'gray' => 'bg-gray-500/90 text-white',
    ];
@endphp

<span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold shadow-sm {{ $colors[$status->getColor()] }} {{ $class ?? '' }}">{{ $status->getLabel() }}</span>
