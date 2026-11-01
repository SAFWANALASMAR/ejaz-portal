{{-- شارة حالة المسابقة: مفتوحة بالأزرق، وقريباً بالأزرق الفاتح، ومكتملة رمادي (الأزرق وحده — البند G1) --}}
@props(['status'])

@php
    use App\Enums\CompetitionStatus;

    $classes = match ($status) {
        CompetitionStatus::Open => 'bg-primary text-white',
        CompetitionStatus::Upcoming => 'bg-brand-light text-primary-deep',
        default => 'bg-gray-500 text-white',
    };
@endphp

<span {{ $attributes->merge(['class' => 'keep-color inline-flex rounded-full px-3 py-1 text-xs font-bold shadow-sm '.$classes]) }}>{{ $status->getLabel() }}</span>
