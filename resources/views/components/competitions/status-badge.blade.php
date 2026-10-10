{{-- شارة حالة المسابقة (الصورة 06): مفتوحة أخضر، وقريباً ذهبي، ومكتملة رمادي --}}
@props(['status'])

@php
    use App\Enums\CompetitionStatus;

    $classes = match ($status) {
        CompetitionStatus::Open => 'bg-emerald-600 text-white',
        CompetitionStatus::Upcoming => 'bg-accent text-ink',
        default => 'bg-gray-500 text-white',
    };
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex rounded-full px-3 py-1 text-xs font-bold shadow-sm '.$classes]) }}>{{ $status->getLabel() }}</span>
