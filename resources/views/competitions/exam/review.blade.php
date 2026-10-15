{{--
    خامسًا: المراجعة والإرسال النهائي. أرقام الأسئلة بحالتها والرجوع لأي سؤال، ثم «إرسال وإنهاء الاختبار»
    بنافذة تأكيد (عنصر popover يعمل بلا سكربت). والإرسال متكرر الأمان في الخادم (SubmitExam)، والزر يُعطَّل
    بعد أول ضغطة (data-once).
--}}
<x-layouts.account :title="__('competitions.exam.title', ['competition' => $competition->title])">
    @include('competitions.exam._steps', ['current' => 'review'])

    <section class="space-y-5 rounded-2xl border border-line/70 bg-white p-6 shadow-xs">
        <h2 class="text-xl font-bold text-primary">{{ __('competitions.exam.review_heading') }}</h2>
        <p class="text-muted">{{ __('competitions.exam.review_text') }}</p>

        @error('answers')
            <x-alert type="error">{{ $message }}</x-alert>
        @enderror

        <ul class="grid gap-2 sm:grid-cols-2">
            @foreach ($questions as $question)
                @php($done = $submission->answer($question) !== null)
                <li>
                    <a href="{{ route('competitions.exam.question', [$competition->slug, $loop->iteration]) }}" wire:navigate
                        class="flex items-center justify-between rounded-xl border border-line px-4 py-3 hover:border-primary">
                        <span class="font-semibold text-ink">{{ __('competitions.exam.question_of', ['number' => $loop->iteration, 'total' => $loop->count]) }}</span>
                        <span @class(['text-sm font-semibold', 'text-primary' => $done, 'text-red-700' => ! $done])>
                            {{ $done ? __('competitions.exam.answered') : __('competitions.exam.unanswered') }}
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>

        <x-button type="button" popovertarget="exam-confirm" arrow>{{ __('competitions.exam.submit') }}</x-button>
    </section>

    <div id="exam-confirm" popover role="dialog" aria-modal="true" aria-labelledby="exam-confirm-text"
        class="m-auto max-w-md rounded-2xl border border-line bg-white p-6 shadow-xl backdrop:bg-black/40">
        <p id="exam-confirm-text" class="leading-8 font-semibold text-ink">{{ __('competitions.exam.submit_confirm') }}</p>
        <form method="POST" action="{{ route('competitions.exam.submit', $competition->slug) }}" data-once class="mt-5 flex flex-wrap gap-3">
            @csrf
            <x-button variant="outline" type="button" popovertarget="exam-confirm" popovertargetaction="hide">{{ __('competitions.exam.back_to_review') }}</x-button>
            <x-button>{{ __('competitions.exam.confirm_submit') }}</x-button>
        </form>
    </div>
</x-layouts.account>
