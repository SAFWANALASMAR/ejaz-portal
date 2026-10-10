{{--
    طباعة النموذج المعبأ بترويسة الجمعية (requirements/05). الترويسة مؤقتة معلَّمة حتى يصل دليل الهوية
    (open-questions.md)، وحقول المعبأ من إصداره، والتواقيع صورها. ويُطبع بـ mPDF، وأنماطه في
    documents/form-entry-pdf-style (PrintFormEntry).
--}}
@php
    use App\Enums\FormEntryStatus;
    use App\Support\Dates;
@endphp

<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
</head>
<body>
    <div class="letterhead">
        <div class="org">{{ __('site.name') }}</div>
        <div class="temporary">{{ __('documents.print.temporary_letterhead') }}</div>
    </div>

    <h1>{{ $entry->version->template->title }}</h1>
    <div class="meta">
        {{ __('documents.print.reference') }}: {{ $entry->reference }}
        — {{ __('documents.print.version', ['version' => $entry->version->version]) }}
        — {{ $entry->status->getLabel() }}
        @if ($entry->status === FormEntryStatus::Approved && $entry->approved_at)
            — {{ __('documents.print.approved', ['date' => Dates::display($entry->approved_at), 'name' => $entry->approver?->name ?? '—']) }}
        @endif
    </div>

    <table>
        @foreach ($rows as $row)
            <tr>
                <td class="label">{{ $row['label'] }}</td>
                <td>
                    @if ($row['signature'])
                        <img class="signature" src="{{ $row['signature'] }}" alt="">
                    @else
                        {{ $row['value'] ?? '—' }}
                    @endif
                </td>
            </tr>
        @endforeach
    </table>

    <div class="footer">
        {{ __('documents.print.filled_by', ['name' => $entry->creator?->name ?? '—', 'date' => Dates::display($entry->created_at)]) }}
    </div>
</body>
</html>
