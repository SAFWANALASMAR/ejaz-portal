{{--
    مسابقة الطلاب في «مشاركاتي»: التسجيل برقمه، ولكل مرحلة دخلها الطالب حالتها، والدرجة بعد نشر نتائج المرحلة
    وحدها (قرار المدير التنفيذي: لا تظهر قبل النشر)، والشهادة حين تصدر.
--}}
@php
    use App\Support\Dates;
@endphp

<li class="space-y-4 rounded-2xl border border-line/70 bg-white p-5 shadow-xs">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="font-bold text-ink">{{ $submission->competition->title }}</p>
            <p class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted">
                <span>{{ __('competitions.student.registered_status') }}</span>
                @if ($submission->reference)
                    <span>{{ __('competitions.entry.reference') }}: <span dir="ltr" class="font-semibold text-primary">{{ $submission->reference }}</span></span>
                @endif
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($submission->certificate_path)
                <x-button :href="route('account.competitions.certificate', $submission->uuid)" size="sm">
                    {{ \App\Support\Icon::svg('arrow-down-tray', 'size-4', ['aria-hidden' => 'true']) }}
                    {{ __('competitions.student.certificate') }}
                </x-button>
            @endif
            <x-button :href="route('competitions.show', $submission->competition->slug)" variant="outline" size="sm">{{ __('competitions.account.open') }}</x-button>
        </div>
    </div>

    @if ($submission->attempts->isNotEmpty())
        <ul class="divide-y divide-line/70 rounded-xl border border-line/70">
            @foreach ($submission->attempts->sortBy(fn ($attempt) => [$attempt->round->sort_order, $attempt->round->id]) as $attempt)
                <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm">
                    <span class="font-semibold text-ink">{{ $attempt->round->name }}
                        @if ($attempt->reference)
                            <span class="ms-2 text-xs font-normal text-muted">{{ __('competitions.student.attempt_reference') }}: <span dir="ltr" class="font-semibold text-primary">{{ $attempt->reference }}</span></span>
                        @endif
                    </span>
                    @if ($attempt->isInProgress())
                        <span class="text-muted">{{ __('competitions.student.in_progress') }}</span>
                    @elseif ($attempt->round->resultsPublished())
                        <span class="font-bold text-primary">{{ __('competitions.student.your_score', ['score' => \Illuminate\Support\Number::format((float) $attempt->score + (float) $attempt->essay_score, maxPrecision: 2), 'max' => \Illuminate\Support\Number::format((float) $attempt->max_score, maxPrecision: 2)]) }}</span>
                    @else
                        <span class="text-muted">{{ __('competitions.student.received_short') }} · {{ Dates::display($attempt->submitted_at) }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</li>
