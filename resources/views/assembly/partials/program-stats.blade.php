{{--
    «إحصائيات البرامج لكل نوع» في بوابة المجلس (البند R23 — docs/decisions/2026-11-02-program-stats-by-type.md): لمن له stats.view
    كلوحة الإحصائيات (عضو المجلس ومديرا النظام)، من المخزّن الذي يحسبه ejaz:refresh-stats وحده، فلا استعلام عند الطلب. ولكل نوع
    أرقامه ونسبة إكماله وشريطا مقارنة بالنسبة إلى أكثر الأنواع تسجيلاً. ولا يظهر القسم قبل أول حساب.
--}}
@php($programStats = \App\Support\ProgramStatsByType::visibleTo($user))

@if ($programStats)
    @php($programStatsMax = max(1, ...array_column($programStats, 'registered')))
    <section class="concept-panel rounded-[1.25rem] p-5 sm:p-6" aria-labelledby="board-program-stats" data-board-program-stats>
        <h2 id="board-program-stats" class="flex items-center gap-2.5 text-[1.375rem] font-black text-primary-dark sm:text-2xl">
            <x-icon-tile icon="chart-bar" size="sm" shade="medium" />
            {{ __('program_stats.heading') }}
        </h2>
        <p class="mt-1 text-sm text-muted">{{ __('program_stats.description') }}</p>

        <ul class="mt-4 grid gap-3.5 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($programStats as $row)
                <li class="tone-card flex flex-col gap-3 p-4.5" data-program-stats-row="{{ $row['key'] }}">
                    <div class="flex items-baseline justify-between gap-3">
                        <span class="text-lg font-black">{{ $row['label'] }}</span>
                        <span class="tone-chip tabular-nums" title="{{ __('program_stats.rate') }}">
                            <span class="sr-only">{{ __('program_stats.rate') }}</span>
                            {{ __('program_stats.rate_value', ['rate' => $row['rate']]) }}
                        </span>
                    </div>

                    <dl class="grid gap-2 text-sm">
                        @foreach (['registered' => 'fill-primary-dark', 'completed' => 'fill-brand'] as $field => $fill)
                            @php($width = round($row[$field] / $programStatsMax * 100, 1))
                            <div class="flex flex-col gap-1">
                                <div class="flex justify-between gap-2">
                                    <dt class="tone-card-muted font-bold">{{ __('program_stats.'.$field) }}</dt>
                                    <dd class="font-black tabular-nums">{{ number_format($row[$field]) }}</dd>
                                </div>
                                {{-- الشريط من اليمين كاتجاه القراءة، وزخرفة فالرقم قبله يكفي --}}
                                <svg class="h-2 w-full overflow-hidden rounded-full bg-white/40" viewBox="0 0 100 8" preserveAspectRatio="none" aria-hidden="true" focusable="false">
                                    <rect x="{{ 100 - $width }}" y="0" width="{{ $width }}" height="8" class="{{ $fill }}" />
                                </svg>
                            </div>
                        @endforeach

                        @foreach (['pages', 'minutes'] as $field)
                            @continue($row[$field] === null)
                            <div class="flex justify-between gap-2">
                                <dt class="tone-card-muted font-bold">{{ __('program_stats.'.$field) }}</dt>
                                <dd class="font-black tabular-nums">{{ number_format($row[$field]) }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </li>
            @endforeach
        </ul>
    </section>
@endif
