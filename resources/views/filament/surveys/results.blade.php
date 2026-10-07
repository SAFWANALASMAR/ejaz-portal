{{-- النتائج المجمعة للاستبيان (SurveyResults): المقياس بمتوسطه وتوزيعه، والاختيار بأعداده، والنص بلا أسماء --}}
@php
    use App\Enums\SurveyQuestionType;

    $results = $this->getResults();
@endphp

<x-filament-panels::page>
    <p class="text-sm text-gray-600 dark:text-gray-300">{{ __('surveys.results.responses', ['count' => number_format($results['responses'])]) }}</p>

    @if ($results['withheld'] ?? false)
        <x-filament::section>
            <p class="text-sm text-gray-600 dark:text-gray-300">{{ __('surveys.results.withheld', ['count' => \App\Support\SurveyResults::MIN_RESPONSES]) }}</p>
        </x-filament::section>
    @endif

    @foreach ($results['questions'] as $row)
        <x-filament::section :heading="$loop->iteration.'. '.$row['question']->prompt" :description="__('surveys.results.answered', ['count' => number_format($row['answered'])])">
            @if ($row['answered'] === 0)
                <p class="text-sm text-gray-500">{{ __('surveys.results.no_answers') }}</p>
            @elseif ($row['question']->type === SurveyQuestionType::Text)
                <ul class="space-y-2 text-sm text-gray-950 dark:text-white">
                    @foreach ($row['texts'] as $text)
                        <li class="rounded-lg bg-gray-50 p-3 dark:bg-white/5">{{ $text }}</li>
                    @endforeach
                </ul>
                <p class="mt-2 text-xs text-gray-500">{{ __('surveys.results.texts_limit', ['count' => \App\Support\SurveyResults::TEXT_LIMIT]) }}</p>
            @else
                @if (isset($row['average']))
                    <p class="mb-3 font-semibold text-gray-950 dark:text-white">{{ __('surveys.results.average', ['average' => $row['average']]) }}</p>
                @endif
                <ul class="space-y-2">
                    @foreach ($row['counts'] as $value => $count)
                        @php($percent = $row['answered'] > 0 ? round($count / $row['answered'] * 100) : 0)
                        <li class="text-sm">
                            <div class="flex justify-between text-gray-950 dark:text-white">
                                <span>{{ $row['question']->type === SurveyQuestionType::Choice ? ($row['question']->optionList()[$value] ?? '') : $value }}</span>
                                <span>{{ number_format($count) }} ({{ $percent }}%)</span>
                            </div>
                            <div class="mt-1 h-2 rounded-full bg-gray-100 dark:bg-white/10">
                                <div class="h-2 rounded-full bg-primary-600" style="width: {{ $percent }}%"></div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-filament::section>
    @endforeach
</x-filament-panels::page>
