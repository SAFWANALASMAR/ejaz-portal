{{--
    حالة المحاولة في مرحلة (دليل الدخول: «إذا انقطع الاتصال قبل الإرسال، أعد الدخول وتحقق من حالة المحاولة»): لم تبدأ،
    أو جارية بوقتها الباقي وزر الإكمال، أو مسلَّمة برقم المحاولة ووقت التسليم. بلا درجة قبل نشر النتائج.
--}}
@php
    use App\Support\Dates;
@endphp

<x-layouts.account :title="__('competitions.student.status_title', ['round' => $round->name])">
    <section class="space-y-5 concept-panel p-6">
        <div>
            <h2 class="flex items-center gap-2 text-xl font-black text-primary-dark"><span class="heading-flank-dot" aria-hidden="true"></span>{{ $round->name }}</h2>
            @if ($round->branch)
                <p class="mt-1 font-semibold text-accent">{{ $round->branch }}</p>
            @endif
        </div>

        <div class="space-y-3">
            @if ($attempt === null)
                <x-alert type="info">{{ $round->ends_at->isPast() && ! ($open ?? false) ? __('competitions.student.status_missed') : __('competitions.student.status_not_started') }}</x-alert>
                @if ($open ?? $round->isRunning())
                    <x-button :href="route('competitions.student.round', [$competition->slug, $round->uuid])" arrow>{{ __('competitions.student.enter') }}</x-button>
                @endif
            @elseif ($attempt->isInProgress())
                <x-alert type="warning">{{ __('competitions.student.status_in_progress') }}</x-alert>
                <p class="text-sm text-muted">{{ __('competitions.student.time_left') }}:
                    <span class="font-semibold text-ink">{{ trans_choice('competitions.student.minutes_left', intdiv($attempt->secondsLeft(), 60), ['count' => intdiv($attempt->secondsLeft(), 60)]) }}</span>
                </p>
                <x-button :href="route('competitions.student.round', [$competition->slug, $round->uuid])" arrow>{{ __('competitions.student.resume') }}</x-button>
            @else
                <x-alert type="success">{{ $attempt->auto_submitted ? __('competitions.student.status_auto_submitted') : __('competitions.student.status_submitted') }}</x-alert>
                <dl class="grid gap-3 sm:grid-cols-2">
                    @if ($attempt->reference)
                        <div>
                            <dt class="text-sm text-muted">{{ __('competitions.student.attempt_reference') }}</dt>
                            <dd dir="ltr" class="mt-1 text-start text-lg font-bold tracking-wider text-primary">{{ $attempt->reference }}</dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-sm text-muted">{{ __('competitions.entry.submitted_at') }}</dt>
                        <dd class="mt-1 font-medium text-ink">{{ Dates::display($attempt->submitted_at, withTime: true) }}</dd>
                    </div>
                </dl>
            @endif
        </div>

        <x-button :href="route('competitions.show', $competition->slug)" variant="outline">{{ $competition->title }}</x-button>
    </section>
</x-layouts.account>
