{{-- وصف رسم «إحصائيات البرامج لكل نوع» (ProgramStatsByTypeWidget): لكل نوع أرقامه ونسبة إكماله. داخل <p> القسم، فعناصره سطرية --}}
<span class="block">{{ __('program_stats.description') }}</span>
@foreach ($rows as $row)
    <span class="mt-1 block tabular-nums" data-program-stats-row="{{ $row['key'] }}">
        {{ __('program_stats.summary', ['type' => $row['label'], 'registered' => number_format($row['registered']), 'completed' => number_format($row['completed']), 'rate' => $row['rate']]) }}
        @if ($row['pages'] !== null)
            {{ __('program_stats.summary_pages', ['pages' => number_format($row['pages'])]) }}
        @endif
        @if ($row['minutes'] !== null)
            {{ __('program_stats.summary_minutes', ['minutes' => number_format($row['minutes'])]) }}
        @endif
    </span>
@endforeach
