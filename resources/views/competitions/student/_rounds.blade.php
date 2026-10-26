{{-- مراحل مسابقة الطلاب بنوافذها، ولكل مرحلة حالة الطالب المسجل وزر الدخول في نافذتها وحدها --}}
@php
    use App\Support\Dates;
    use Illuminate\Support\Facades\Storage;

    $rounds = $competition->rounds()->get();
    $attempts = $submission?->attempts()->get()->keyBy('round_id') ?? collect();
@endphp

@if ($rounds->isNotEmpty())
    <div>
        <x-section-heading>{{ __('competitions.student.rounds') }}</x-section-heading>
        @if ($rounds->count() > 1)
            <p class="mt-3 text-sm leading-7 text-muted">{{ __('competitions.student.page.all_branches') }}</p>
        @endif
        @if ($submission && $attempts->isNotEmpty())
            <p class="mt-1 text-sm font-semibold text-primary">{{ __('competitions.student.page.branches_done', ['done' => $attempts->filter(fn ($attempt) => ! $attempt->isInProgress())->count(), 'total' => $rounds->count()]) }}</p>
        @endif
        <ol class="mt-4 space-y-3">
            @foreach ($rounds as $round)
                @php($attempt = $attempts->get($round->id))
                <li class="flex flex-col gap-3 rounded-2xl border border-line/70 bg-white p-5 shadow-xs sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex gap-4">
                    @if ($round->image_path)
                        <img src="{{ Storage::disk('public')->url($round->image_path) }}" alt="" class="size-16 shrink-0 rounded-xl object-cover" loading="lazy">
                    @endif
                    <div class="space-y-1">
                        <p class="font-bold text-ink">{{ $round->name }}</p>
                        @if ($round->branch)
                            <p class="text-sm font-semibold text-accent">{{ $round->branch }}</p>
                        @endif
                        <p class="flex items-center gap-2 text-sm text-muted">
                            {{ svg('heroicon-o-calendar', 'size-4 shrink-0 text-accent', ['aria-hidden' => 'true']) }}
                            {{ Dates::display($round->starts_at, withTime: true) }} — {{ Dates::display($round->ends_at, withTime: true) }}
                        </p>
                        <p class="text-sm text-muted">{{ trans_choice('competitions.student.questions_count', $round->question_count, ['count' => $round->question_count]) }} · {{ trans_choice('competitions.student.minutes', $round->duration_minutes, ['count' => $round->duration_minutes]) }}</p>
                    </div>
                    </div>

                    @if ($submission)
                        @if ($attempt && ! $attempt->isInProgress())
                            <a href="{{ route('competitions.student.status', [$competition->slug, $round->uuid]) }}" class="text-sm font-semibold text-primary underline-offset-4 hover:underline">{{ __('competitions.student.received_short') }}</a>
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
