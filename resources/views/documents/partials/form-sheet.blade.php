{{--
    متن النموذج المطبوع كورقة: العنوان، والرقم المرجعي، ثم كتل الإصدار بترتيبها (FormEntryLayout::blocks).
    مشترك بين طباعة المتصفح وPDF، وبجداول لا flex لأن mPDF لا يفهمه. والقيم كلها تُهرَّب.
--}}
@php
    use App\Enums\FormEntryStatus;
    use App\Support\Dates;
@endphp

<h1 class="sheet-title">{{ $entry->version->template->title }}</h1>

<table class="sheet-meta" role="presentation">
    <tr>
        <td>{{ __('documents.print.reference') }}: <span dir="ltr">{{ $entry->reference }}</span></td>
        <td>{{ __('documents.print.version', ['version' => $entry->version->version]) }}</td>
        <td>{{ $entry->status->getLabel() }}</td>
        @if ($entry->person)
            <td>{{ __('documents.print.person', ['name' => $entry->person->name]) }}</td>
        @endif
    </tr>
</table>

@foreach ($blocks as $block)
    @if ($block['type'] === 'heading')
        <h2 class="sheet-heading">{{ $block['text'] }}</h2>
    @elseif ($block['type'] === 'paragraph')
        <p class="sheet-paragraph">@foreach (explode("\n", $block['text']) as $line){{ $line }}@if (! $loop->last)<br> @endif @endforeach</p>
    @elseif ($block['type'] === 'table')
        <div class="sheet-table-label">{{ $block['label'] }}</div>
        <table class="sheet-table">
            <thead>
                <tr>
                    <th class="index">#</th>
                    @foreach ($block['columns'] as $column)
                        <th>{{ $column }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($block['rows'] as $row)
                    <tr>
                        <td class="index">{{ $loop->iteration }}</td>
                        @foreach ($row as $cell)
                            <td>{{ $cell }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td class="index">&nbsp;</td>
                        @foreach ($block['columns'] as $column)
                            <td>&nbsp;</td>
                        @endforeach
                    </tr>
                @endforelse
            </tbody>
        </table>
    @else
        <table @class(['sheet-row', 'pair' => count($block['cells']) === 2]) role="presentation">
            <tr>
                @foreach ($block['cells'] as $cell)
                    <td class="sheet-label">{{ $cell['label'] }}</td>
                    <td class="sheet-value">
                        @if ($cell['signature'])
                            <img class="signature" src="{{ $cell['signature'] }}" alt="">
                        @else
                            @foreach (explode("\n", $cell['value'] ?? '') as $line){{ $line }}@if (! $loop->last)<br> @endif @endforeach
                        @endif
                    </td>
                @endforeach
            </tr>
        </table>
    @endif
@endforeach

<div class="sheet-footer-note">
    {{ __('documents.print.filled_by', ['name' => $entry->creator?->name ?? '—', 'date' => Dates::display($entry->created_at)]) }}
    @if ($entry->status === FormEntryStatus::Approved && $entry->approved_at)
        — {{ __('documents.print.approved', ['date' => Dates::display($entry->approved_at), 'name' => $entry->approver?->name ?? '—']) }}
    @endif
</div>
