{{--
    بطاقة اجتماع (الصورة 05 — الجوال): أيقونة تقويم والتاريخ، ثم العنوان، ثم زر «عرض التفاصيل».
--}}
@php
    use App\Enums\MeetingStatus;
    use App\Support\Dates;
@endphp

<article class="flex h-full flex-col rounded-2xl border border-line/70 bg-white p-5 text-center shadow-xs">
    <span class="mx-auto flex size-12 items-center justify-center rounded-full bg-primary-soft text-primary" aria-hidden="true">
        {{ svg('heroicon-o-calendar-days', 'size-6') }}
    </span>
    <p class="mt-3 text-sm font-semibold text-gold-dark">{{ Dates::display($meeting->starts_at, withTime: true) }}</p>
    <h3 class="mt-2 text-lg font-bold text-ink">{{ $meeting->title }}</h3>
    <p class="mt-2 flex flex-wrap items-center justify-center gap-2 text-xs">
        <span class="rounded-full bg-sand px-3 py-1 font-semibold text-muted">{{ __('assembly.meeting.model') }} {{ $meeting->type->getLabel() }}</span>
        @if ($meeting->status !== MeetingStatus::Scheduled)
            <span class="rounded-full px-3 py-1 font-semibold {{ $meeting->status === MeetingStatus::Held ? 'bg-primary text-white' : 'bg-gray-500/90 text-white' }}">{{ $meeting->status->getLabel() }}</span>
        @endif
    </p>
    <x-button class="mt-auto w-full" size="sm" :href="route('assembly.meetings.show', $meeting)">{{ __('assembly.meetings.details') }}</x-button>
</article>
