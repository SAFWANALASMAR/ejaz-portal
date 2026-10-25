{{-- مراحل مسابقة الطلاب بنوافذها، ولكل مرحلة حالة الطالب المسجل وزر الدخول في نافذتها وحدها --}}
@php
    use App\Support\Dates;

    $rounds = $competition->rounds()->get();
    $attempts = $submission?->attempts()->get()->keyBy('round_id') ?? collect();
@endphp

@if ($rounds->isNotEmpty())
    <div>
        <x-section-heading>{{ __('competitions.student.rounds') }}</x-section-heading>
        <ol class="mt-4 space-y-3">
            @foreach ($rounds as $round)
                @php($attempt = $attempts->get($round->id))
                <li class="flex flex-col gap-3 rounded-2xl border border-line/70 bg-white p-5 shadow-xs sm:flex-row sm:items-center sm:justify-between">
                    <div class="space-y-1">
                        <p class="font-bold text-ink">{{ $round->name }}</p>
                        <p class="flex items-center gap-2 text-sm text-muted">
                            {{ svg('heroicon-o-calendar', 'size-4 shrink-0 text-accent', ['aria-hidden' => 'true']) }}
                            {{ Dates::display($round->starts_at, withTime: true) }} — {{ Dates::display($round->ends_at, withTime: true) }}
                        </p>
                        <p class="text-sm text-muted">{{ trans_choice('competitions.student.questions_count', $round->question_count, ['count' => $round->question_count]) }} · {{ trans_choice('competitions.student.minutes', $round->duration_minutes, ['count' => $round->duration_minutes]) }}</p>
                    </div>

                    @if ($submission)
                        @if ($attempt && ! $attempt->isInProgress())
                            <span class="text-sm font-semibold text-primary">{{ __('competitions.student.received_short') }}</span>
                        @elseif ($round->isRunning())
                            <x-button :href="route('competitions.student.round', [$competition->slug, $round->uuid])" size="sm" arrow>
                                {{ $attempt ? __('competitions.student.resume') : __('competitions.student.enter') }}
                            </x-button>
                        @elseif ($round->starts_at->isFuture())
                            <span class="text-sm text-muted">{{ __('competitions.student.upcoming') }}</span>
                        @else
                            <span class="text-sm text-muted">{{ __('competitions.student.ended') }}</span>
                        @endif
                    @endif
                </li>
            @endforeach
        </ol>
    </div>
@endif
