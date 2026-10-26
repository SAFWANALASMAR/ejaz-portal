{{--
    شهادة المشاركة في مسابقة الطلاب: صورة الخلفية التي يرفعها المسؤول لكل مسابقة إن وُجدت، وفوقها اسم الطالب
    والمسابقة والتاريخ. وبلا خلفية: القالب المؤقت بعلامة واضحة. والقالب المعتمد من الجمعية 【ناقص】
    (open-questions.md). يُطبع بـ mPDF، وأنماطه في certificates/competition-style (GenerateCompetitionCertificate).
--}}
@php
    use App\Support\Dates;
@endphp

<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
</head>
<body>
    @if ($hasBackground)
        <div class="background"><img src="var:background" alt=""></div>
    @endif

    <div @class(['frame', 'plain' => ! $hasBackground])>
        @unless ($hasBackground)
            <div class="temporary">{{ __('competitions.student.certificate_pdf.temporary') }}</div>
            <div class="org">{{ __('site.name') }}</div>
        @endunless
        <h1>{{ __('competitions.student.certificate_pdf.title') }}</h1>
        <div class="line"></div>

        <div class="label">{{ __('competitions.student.certificate_pdf.name') }}</div>
        <div class="value">{{ $submission->full_name }}</div>

        <div class="label">{{ __('competitions.student.certificate_pdf.competition') }}</div>
        <div class="value">{{ $submission->competition->title }}</div>

        <div class="label">{{ __('competitions.student.certificate_pdf.date') }}</div>
        <div class="value small">{{ Dates::display(now()) }}</div>

        {{-- كمكوّن x-missing: لا علامة نقص في الإنتاج (والشهادة لا تُصدر فيه قبل قالب الجمعية) --}}
        @unless ($hasBackground || app()->isProduction())
            <div class="missing">[{{ __('site.missing') }}: {{ __('competitions.student.certificate_pdf.missing') }}]</div>
        @endunless
        <div class="ref">{{ $submission->reference }}</div>
    </div>
</body>
</html>
