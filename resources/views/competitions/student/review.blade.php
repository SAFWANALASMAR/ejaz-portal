{{--
    المراجعة قبل التسليم: الوحدات بحالة أسئلتها والرجوع لأي منها، ثم «تسليم الإجابات» بنافذة تأكيد (popover بلا
    سكربت). والعداد يسلّم آلياً عند انتهاء الوقت. والتسليم متكرر الأمان في الخادم (SubmitAttempt).
--}}
<x-layouts.account :title="__('competitions.student.round_title', ['round' => $round->name, 'competition' => $competition->title])">
    @include('competitions.student._timer')

    <section class="space-y-5 rounded-2xl border border-line/70 bg-white p-6 shadow-xs">
        <h2 class="text-xl font-bold text-primary">{{ __('competitions.exam.review_heading') }}</h2>
        <p class="text-muted">{{ __('competitions.student.review_text') }}</p>

        <ul class="grid gap-2 sm:grid-cols-2">
            @foreach ($attempt->layout as $index => $unit)
                @php
                    $count = count($unit['questions']);
                    $answered = collect($unit['questions'])->filter(fn ($q) => $attempt->answer((int) $q['id']) !== null)->count();
                @endphp
                <li>
                    <a href="{{ route('competitions.student.unit', [$competition->slug, $round->uuid, $index + 1]) }}" wire:navigate
                        class="flex items-center justify-between rounded-xl border border-line px-4 py-3 hover:border-primary">
                        <span class="font-semibold text-ink">{{ __('competitions.student.unit_of', ['number' => $index + 1, 'total' => $loop->count]) }}</span>
                        <span @class(['text-sm font-semibold', 'text-primary' => $answered === $count, 'text-red-700' => $answered < $count])>
                            {{ __('competitions.student.answered_of', ['answered' => $answered, 'count' => $count]) }}
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>

        <x-button type="button" popovertarget="exam-confirm" arrow>{{ __('competitions.student.submit') }}</x-button>
    </section>

    {{-- نموذج التسليم: يرسله العداد آلياً عند انتهاء الوقت --}}
    <div id="exam-confirm" popover role="dialog" aria-modal="true" aria-labelledby="exam-confirm-text"
        class="m-auto max-w-md rounded-2xl border border-line bg-white p-6 shadow-xl backdrop:bg-black/40">
        <p id="exam-confirm-text" class="leading-8 font-semibold text-ink">{{ __('competitions.student.submit_confirm') }}</p>
        <form method="POST" action="{{ route('competitions.student.submit', [$competition->slug, $round->uuid]) }}" data-once data-exam-autosubmit class="mt-5 flex flex-wrap gap-3">
            @csrf
            <input type="hidden" name="auto" value="0">
            <x-button variant="outline" type="button" popovertarget="exam-confirm" popovertargetaction="hide">{{ __('competitions.exam.back_to_review') }}</x-button>
            <x-button>{{ __('competitions.exam.confirm_submit') }}</x-button>
        </form>
    </div>
</x-layouts.account>
