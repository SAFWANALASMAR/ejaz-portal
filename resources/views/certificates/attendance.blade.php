{{--
    شهادة الحضور — قالب مؤقت (قرار صفوان 7 أكتوبر 2026): بيانات المسجل والبرنامج وحدها، وعلامة
    واضحة أنه مؤقت. النص والتصميم والتوقيع والختم من الجمعية لاحقاً (open-questions.md).
    يُطبع بـ mPDF، فالأنماط بسيطة ومضمّنة.
--}}
@php
    use App\Support\Dates;

    $program = $registration->program;
@endphp

<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: xbriyaz; color: #1C2B2A; text-align: center; }
        .frame { border: 6px double #1F4F4B; padding: 28px 40px; height: 150mm; }
        .temporary { background: #F5F1EA; border: 1px dashed #B8955A; color: #8a6d3b; padding: 6px; font-size: 13pt; }
        .org { color: #1F4F4B; font-size: 18pt; margin-top: 18px; }
        h1 { color: #1F4F4B; font-size: 34pt; margin: 18px 0 8px; }
        .line { width: 70px; border-top: 3px solid #B8955A; margin: 0 auto 24px; }
        .label { color: #6B7775; font-size: 13pt; }
        .value { font-size: 22pt; font-weight: bold; margin-bottom: 14px; }
        .missing { color: #8a6d3b; font-size: 12pt; margin-top: 18px; }
        .ref { color: #6B7775; font-size: 10pt; margin-top: 14px; direction: ltr; }
    </style>
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
