{{--
    عرض النموذج المعبأ في المتصفح كورقة وورد على A4، وطباعته منه (requirements/12 البند 10: «كأنه ملف وورد
    أو كأنه مطبوع يعني على ورق»). المتن والترويسة كما في PDF (documents/partials)، وبخط Zain من أصول البناء.
    الترويسة في رأس جدول يتكرر في كل صفحة مطبوعة، ورقم الصفحة في هامشها، وعلى غير المعتمد علامة «مسودة».
--}}
@php
    use App\Enums\FormEntryStatus;
    use Illuminate\Support\Facades\Vite;
@endphp

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $entry->version->template->title }} — {{ $entry->reference }}</title>
    @fonts
    <style>
        @page { size: A4; margin: 20mm 20mm 22mm; @bottom-left { content: counter(page) " / " counter(pages); font-size: 9pt; color: #444; } }
        * { box-sizing: border-box; }
        html { background: #E5E7EB; }
        body { margin: 0; font-family: 'Zain', system-ui, sans-serif; color: #111; font-size: 12pt; line-height: 1.6; }
        .toolbar { position: sticky; top: 0; z-index: 10; display: flex; flex-wrap: wrap; gap: 8px; align-items: center; justify-content: space-between; padding: 10px 16px; background: #fff; border-bottom: 1px solid #D1D5DB; }
        .toolbar button, .toolbar a { font: inherit; padding: 6px 16px; border-radius: 8px; border: 1px solid #2E6898; cursor: pointer; text-decoration: none; }
        .toolbar button { background: #2E6898; color: #fff; }
        .toolbar a { background: #fff; color: #2E6898; }
        .paper { position: relative; width: 210mm; max-width: calc(100% - 32px); min-height: 297mm; margin: 24px auto; padding: 20mm; background: #fff; box-shadow: 0 2px 12px rgba(0, 0, 0, .15); overflow: hidden; }
        .watermark { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; pointer-events: none; font-size: 120pt; font-weight: 800; color: rgba(46, 104, 152, .08); transform: rotate(-35deg); }
        .page { width: 100%; border-collapse: collapse; }
        .page > thead > tr > td, .page > tbody > tr > td { padding: 0; }
        .letterhead { width: 100%; border-bottom: 2px solid #2E6898; padding-bottom: 6px; margin-bottom: 14px; border-collapse: collapse; }
        .letterhead td { vertical-align: middle; }
        .letterhead-logo { text-align: end; width: 40%; }
        .logo { height: 16mm; width: auto; }
        .org-name { color: #2E6898; font-size: 15pt; font-weight: 700; }
        .org-contact { color: #444; font-size: 9.5pt; }
        .missing { color: #1F4565; }
        .sheet-title { text-align: center; font-size: 17pt; font-weight: 700; margin: 4px 0 6px; }
        .sheet-meta { width: 100%; font-size: 9.5pt; color: #444; margin-bottom: 10px; }
        .sheet-heading { font-size: 13pt; font-weight: 700; background: #E9EEF4; border: 1px solid #555; padding: 4px 8px; margin: 12px 0 0; }
        .sheet-paragraph { margin: 8px 0; text-align: justify; }
        .sheet-row { width: 100%; border-collapse: collapse; margin-top: -1px; page-break-inside: avoid; }
        .sheet-row td { border: 1px solid #555; padding: 6px 8px; vertical-align: top; }
        .sheet-label { width: 25%; background: #F5F5F5; font-weight: 700; }
        .pair .sheet-label { width: 15%; }
        .pair .sheet-value { width: 35%; }
        .sheet-table-label { font-weight: 700; margin: 10px 0 4px; }
        .sheet-table { width: 100%; border-collapse: collapse; }
        .sheet-table th, .sheet-table td { border: 1px solid #555; padding: 5px 8px; text-align: start; }
        .sheet-table th { background: #F5F5F5; }
        .sheet-table .index { width: 8%; text-align: center; }
        .sheet-table tr { page-break-inside: avoid; }
        .signature { height: 55px; }
        .sheet-footer-note { color: #444; font-size: 9.5pt; margin-top: 16px; }
        @media print {
            html { background: #fff; }
            .toolbar { display: none; }
            .paper { width: auto; max-width: none; min-height: 0; margin: 0; padding: 0; box-shadow: none; overflow: visible; }
            .watermark { position: fixed; }
            .sheet-heading, .sheet-label, .sheet-table th { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <strong>{{ $entry->version->template->title }}</strong>
        <span>
            <button type="button" id="print-sheet">{{ __('documents.print.print_now') }}</button>
            @if ($pdfUrl)
                <a href="{{ $pdfUrl }}">{{ __('documents.actions.print') }}</a>
            @endif
            <a href="{{ $backUrl }}">{{ __('documents.print.back') }}</a>
        </span>
    </div>

    <main class="paper">
        @if ($entry->status === FormEntryStatus::Draft)
            <div class="watermark" aria-hidden="true">{{ __('documents.print.draft') }}</div>
        @endif

        <table class="page" role="presentation">
            <thead>
                <tr><td>@include('documents.partials.form-letterhead', ['logo' => asset('images/brand/logo-horizontal.svg')])</td></tr>
            </thead>
            <tbody>
                <tr><td>@include('documents.partials.form-sheet')</td></tr>
            </tbody>
        </table>
    </main>

    <script nonce="{{ Vite::cspNonce() }}">
        document.getElementById('print-sheet').addEventListener('click', () => window.print());
    </script>
</body>
</html>
