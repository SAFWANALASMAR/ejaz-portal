{{--
    شهادة الحضور — قالب مؤقت (قرار صفوان 7 أكتوبر 2026): بيانات المسجل والبرنامج وحدها، وعلامة
    واضحة أنه مؤقت. النص والتصميم والتوقيع والختم من الجمعية لاحقاً (open-questions.md).
    يُطبع بـ mPDF، وأنماطه في certificates/attendance-style (SyncAttendanceCertificate).
--}}
@php
    use App\Support\Dates;

    $program = $registration->program;
@endphp

<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
</head>
<body>
    <div class="frame">
        <div class="temporary">{{ __('programs.certificate.temporary') }}</div>
        <div class="org">{{ __('site.name') }}</div>
        <h1>{{ __('programs.certificate.title') }}</h1>
        <div class="line"></div>

        <div class="label">{{ __('programs.certificate.name') }}</div>
        <div class="value">{{ $registration->user->name }}</div>

        <div class="label">{{ __('programs.certificate.program') }}</div>
        <div class="value">{{ $program->title }}</div>

        <div class="label">{{ __('programs.certificate.date') }}</div>
        <div class="value">{{ Dates::display($program->starts_at) }}</div>

        <div class="missing">[{{ __('site.missing') }}: {{ __('programs.certificate.missing') }}]</div>
        <div class="ref">{{ $registration->uuid }}</div>
    </div>
</body>
</html>
