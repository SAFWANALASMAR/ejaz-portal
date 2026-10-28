{{--
    رابعًا: سؤال في كل صفحة برقمه من الإجمالي وشريط التقدم، ومعه نص علمي أو وسيط اختياري، وأربعة خيارات.
    كل زر (السابق، والتالي، ورقم سؤال) يحفظ الإجابة في الخادم ثم ينتقل (SaveExamAnswer). ولا يصل الخيار
    الصحيح إلى الصفحة: الأسئلة من الذاكرة المؤقتة بلا عموده (ExamQuestions).
--}}
@php
    use App\Enums\QuestionMedia;

    $current = $submission->answer($question);
@endphp

<x-layouts.account :title="__('competitions.exam.title', ['competition' => $competition->title])">
    @include('competitions.exam._steps', ['current' => 'questions'])

    <form method="POST" action="{{ route('competitions.exam.answer', $competition->slug) }}" class="space-y-5">
        @csrf
        <input type="hidden" name="question" value="{{ $question->id }}">

        <section class="space-y-5 rounded-2xl border border-line/70 bg-white p-6 shadow-xs">
            <div class="space-y-2">
                <p class="text-sm font-semibold text-accent-dark">{{ __('competitions.exam.question_of', ['number' => $number, 'total' => $total]) }}</p>
                <label class="block text-sm text-muted">
                    {{ __('competitions.exam.progress') }}
                    <progress max="{{ $total }}" value="{{ $number }}" class="mt-1 block h-2 w-full appearance-none overflow-hidden rounded-full bg-line forced-colors:appearance-auto [&::-moz-progress-bar]:rounded-full [&::-moz-progress-bar]:bg-primary [&::-webkit-progress-bar]:bg-line [&::-webkit-progress-value]:rounded-full [&::-webkit-progress-value]:bg-primary">{{ $number }} / {{ $total }}</progress>
                </label>
            </div>

            @if (filled($question->context_text))
                <p class="rounded-xl bg-surface p-4 leading-8 whitespace-pre-line text-ink">{{ $question->context_text }}</p>
            @endif

            @if ($question->media_type !== null && filled($question->media_path))
                @php($media = route('competitions.exam.media', [$competition->slug, $number]))
                @switch($question->media_type)
                    @case(QuestionMedia::Image)
                        <img src="{{ $media }}" alt="{{ __('competitions.exam.media_label') }}" class="max-h-96 w-auto rounded-xl">
                        @break
                    @case(QuestionMedia::Video)
                        <video src="{{ $media }}" controls controlslist="nodownload" preload="metadata" playsinline class="aspect-video w-full rounded-xl bg-black" aria-label="{{ __('competitions.exam.media_label') }}"></video>
                        @break
                    @case(QuestionMedia::Audio)
                        <audio src="{{ $media }}" controls controlslist="nodownload" preload="metadata" class="w-full" aria-label="{{ __('competitions.exam.media_label') }}"></audio>
                        @break
                @endswitch
            @endif

            <fieldset class="space-y-3">
                <legend class="mb-3 leading-8 font-medium whitespace-pre-line text-ink">{{ $question->prompt }}</legend>
                @foreach ($options as $option)
                    <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-line px-4 py-3 has-checked:border-primary has-checked:bg-primary-soft">
                        <input type="radio" name="option" value="{{ $submission->optionToken($question->id, $option['key']) }}" class="accent-primary" @checked($current === $option['key'])>
                        <span class="text-ink">{{ $option['label'] }}</span>
                    </label>
                @endforeach
            </fieldset>

            {{-- «التالي» أول زر في الشيفرة فيكون زر Enter، وموضعه بصرياً بعد «السابق» (order-last) --}}
            <div class="flex flex-wrap justify-between gap-3">
                <x-button name="go" value="next" class="order-last" arrow>{{ $number === $total ? __('competitions.exam.to_review') : __('competitions.exam.next') }}</x-button>
                <x-button variant="outline" name="go" value="prev" :disabled="$number === 1">{{ __('competitions.exam.previous') }}</x-button>
            </div>
        </section>

        <nav aria-label="{{ __('competitions.exam.questions_list') }}" class="rounded-2xl border border-line/70 bg-white p-5 shadow-xs">
            <p class="mb-3 text-sm font-semibold text-ink">{{ __('competitions.exam.questions_list') }}</p>
            <ul class="flex flex-wrap gap-2">
                @foreach ($questions as $item)
                    @php($done = $submission->answer($item) !== null)
                    <li>
                        <button type="submit" name="go" value="{{ $loop->iteration }}"
                            aria-label="{{ __('competitions.exam.go_to', ['number' => $loop->iteration, 'status' => $done ? __('competitions.exam.answered') : __('competitions.exam.unanswered')]) }}"
                            @if ($loop->iteration === $number) aria-current="step" @endif
                            @class([
                                'size-10 rounded-full text-sm font-semibold',
                                'bg-primary text-white' => $done,
                                'border border-line bg-white text-ink' => ! $done,
                                'ring-2 ring-accent ring-offset-2' => $loop->iteration === $number,
                            ])>{{ $loop->iteration }}</button>
                    </li>
                @endforeach
                <li>
                    <button type="submit" name="go" value="review" class="h-10 rounded-full border border-primary px-4 text-sm font-semibold text-primary">{{ __('competitions.exam.to_review') }}</button>
                </li>
            </ul>
            <p class="mt-3 flex flex-wrap gap-4 text-sm text-muted">
                <span><span class="inline-block size-3 rounded-full bg-primary align-middle" aria-hidden="true"></span> {{ __('competitions.exam.answered') }}</span>
                <span><span class="inline-block size-3 rounded-full border border-line align-middle" aria-hidden="true"></span> {{ __('competitions.exam.unanswered') }}</span>
            </p>
        </nav>
    </form>
</x-layouts.account>
