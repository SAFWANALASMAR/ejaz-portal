{{-- تقارير تحليل الإحصائيات: نص المزود، وبجانبه الأرقام التي بناها الخادم (StatsAnalysisFigures). والنص يُعرض نصاً --}}
<x-filament-panels::page>
    <div @if ($this->hasQueued()) wire:poll.5s @endif style="display: grid; gap: 1.5rem;">
        @forelse ($this->analyses() as $analysis)
            <x-filament::section
                :heading="__('analysis.period', ['from' => $analysis->period_from->toDateString(), 'to' => $analysis->period_to->toDateString()])"
                :description="__('analysis.requested_at', ['at' => $analysis->created_at->timezone(config('app.display_timezone'))->format('Y-m-d H:i')])">
                <x-slot name="afterHeader">
                    <x-filament::badge :color="$analysis->status->getColor()">{{ $analysis->status->getLabel() }}</x-filament::badge>
                </x-slot>

                <div style="display: grid; gap: 1.5rem; grid-template-columns: repeat(auto-fit, minmax(18rem, 1fr));">
                    <div>
                        <h3 style="font-weight: 600; margin-bottom: 0.5rem;">{{ __('analysis.report') }}</h3>
                        @if ($analysis->status === \App\Enums\StatsAnalysisStatus::Done)
                            <p style="white-space: pre-line; line-height: 1.9;">{{ $analysis->report }}</p>
                            <p style="margin-top: 0.75rem; font-size: 0.8rem; opacity: 0.7;">{{ __('analysis.disclaimer') }}</p>
                        @elseif ($analysis->status === \App\Enums\StatsAnalysisStatus::Queued)
                            <p>{{ __('analysis.waiting') }}</p>
                        @else
                            <p>{{ __('analysis.failed') }}</p>
                        @endif
                    </div>

                    <div>
                        <h3 style="font-weight: 600; margin-bottom: 0.5rem;">{{ __('analysis.figures_heading') }}</h3>
                        @foreach ($analysis->figures as $group => $rows)
                            @if ($rows !== [])
                                <table style="width: 100%; margin-bottom: 1rem; font-size: 0.9rem;">
                                    <caption style="text-align: start; font-weight: 600; padding-bottom: 0.25rem;">{{ __('analysis.groups.'.$group) }}</caption>
                                    <tbody>
                                        @foreach ($rows as $row)
                                            <tr style="border-top: 1px solid rgb(0 0 0 / 0.08);">
                                                <th scope="row" style="text-align: start; font-weight: 400; padding: 0.25rem 0;">{{ $row['label'] }}</th>
                                                <td style="text-align: end; padding: 0.25rem 0; font-variant-numeric: tabular-nums;">{{ number_format($row['value']) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @endif
                        @endforeach
                    </div>
                </div>
            </x-filament::section>
        @empty
            <x-filament::section>
                <p>{{ __('analysis.empty') }}</p>
            </x-filament::section>
        @endforelse
    </div>
</x-filament-panels::page>
