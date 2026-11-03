{{--
    شهادة الحضور — قالب مؤقت (قرار صفوان 7 أكتوبر 2026): بيانات المسجل والبرنامج وحدها، وعلامة
    واضحة أنه مؤقت. النص والتصميم والتوقيع والختم من الجمعية لاحقاً (open-questions.md).
    يُطبع بـ mPDF، وأنماطه في certificates/attendance-style (SyncAttendanceCertificate).
--}}
@php
    use App\Support\Dates;

    $program = $registration->program;
    // شهادة إتمام برنامج المكتبة: باسم المشارك واسم العنصر ووقت الإتمام (docs/decisions/2026-11-02-reading-program.md)
    $completion = $registration->completed_at !== null;
@endphp

<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
</head>
<body>
    <div class="frame">
        <div class="temporary">{{ __('programs.certificate.temporary') }}</div>
        <div class="org">{{ __('site.name') }}</div>
        <h1>{{ $completion ? __('programs.certificate.completion_title') : __('programs.certificate.title') }}</h1>
        <div class="line"></div>

        <div class="label">{{ __('programs.certificate.name') }}</div>
        <div class="value">{{ $registration->user->name }}</div>

        <div class="label">{{ __('programs.certificate.program') }}</div>
        <div class="value">{{ $program->title }}</div>

        @if ($completion && $program->libraryItem !== null)
            <div class="label">{{ __('programs.certificate.item') }}</div>
            <div class="value">{{ $program->libraryItem->title }}</div>
        @endif

        <div class="label">{{ __('programs.certificate.date') }}</div>
        <div class="value">{{ Dates::display($completion ? $registration->completed_at : $program->starts_at) }}</div>

        {{-- كمكوّن x-missing: لا علامة نقص في الإنتاج (والشهادة لا تُصدر فيه قبل قالب الجمعية) --}}
        @unless (app()->isProduction())
            <div class="missing">[{{ __('site.missing') }}: {{ __('programs.certificate.missing') }}]</div>
        @endunless
        <div class="ref">{{ $registration->uuid }}</div>
    </div>
</body>
</html>
