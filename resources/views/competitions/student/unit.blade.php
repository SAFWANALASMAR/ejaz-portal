{{--
    وحدة من اختبار الطالب في صفحة: النص المقروء أو مقطع يوتيوب، ثم أسئلته مرقمة من الإجمالي بخياراتها بترتيب
    الطالب. كل زر (السابقة، والتالية، ورقم وحدة، والمراجعة) يحفظ الإجابات في الخادم ثم ينتقل (SaveAttemptAnswers)،
    وانتهاء العداد يرسل النموذج بالتسليم الآلي. ولا يصل الخيار الصحيح إلى الصفحة (RoundQuestions بلا عموده).
--}}
@php
    use App\Enums\PassageKind;
    use App\Enums\QuestionType;

    $questionTotal = count($attempt->questionIds());
@endphp

<x-layouts.account :title="__('competitions.student.round_title', ['round' => $round->name, 'competition' => $competition->title])">
    @include('competitions.student._timer')

    <form method="POST" action="{{ route('competitions.student.save', [$competition->slug, $round->uuid, $number]) }}" data-exam-autosubmit class="space-y-5">
        @csrf
        {{-- يضبطه العداد عند انتهاء الوقت، فيُحفظ ما في الصفحة ثم تُسلَّم المحاولة --}}
        <input type="hidden" name="go" value="next" disabled data-exam-auto-go>
        <input type="hidden" name="auto" value="0">

        <section class="space-y-6 concept-panel p-5 sm:p-6">
            <p class="text-sm font-semibold text-accent-dark">{{ __('competitions.student.unit_of', ['number' => $number, 'total' => $total]) }}</p>

            @if ($passage)
                <div class="space-y-3 rounded-xl bg-surface p-4">
                    @if (filled($passage->title))
                        <h2 class="font-bold text-primary">{{ $passage->title }}</h2>
                    @endif

                    @if ($passage->kind === PassageKind::Text)
                        <p class="leading-9 whitespace-pre-line text-ink">{{ $passage->body }}</p>
                    @elseif ($youtube = $passage->youtubeId())
                        {{-- لا يُحمَّل مشغّل يوتيوب حتى يضغط الطالب (كالمكتبة المرئية)، وبلا سكربت يفتح المقطع في تبويب جديد --}}
                        <a href="https://www.youtube.com/watch?v={{ $youtube }}" target="_blank" rel="noopener noreferrer"
                            data-video-embed="https://www.youtube-nocookie.com/embed/{{ $youtube }}?autoplay=1"
                            data-video-title="{{ __('competitions.student.video_player') }}"
                            class="group flex aspect-video w-full flex-col items-center justify-center gap-3 rounded-xl bg-primary-dark p-6 text-center text-white">
                            <span class="inline-flex size-16 items-center justify-center rounded-full bg-white/10 transition group-hover:bg-white/20" aria-hidden="true">
                                {{ \App\Support\Icon::solid('play', 'size-8') }}
                            </span>
                            <span class="font-semibold">{{ __('competitions.student.play_video') }}</span>
                        </a>
                        @if (filled($passage->body))
                            <p class="leading-8 whitespace-pre-line text-ink">{{ $passage->body }}</p>
                        @endif
                    @endif
                </div>
            @endif

            @foreach ($unit['questions'] as $item)
                @php
                    $question = $questions->get($item['id']);
                    $labels = collect($question?->optionList() ?? [])->pluck('label', 'key');
                    $current = $attempt->answer((int) $item['id']);
                    $position = $offset + $loop->iteration;
                @endphp
                @continue($question === null)

                <fieldset class="space-y-3">
                    <legend class="mb-3 leading-8 font-medium whitespace-pre-line text-ink">
                        <span class="block text-sm font-semibold text-accent-dark">{{ __('competitions.exam.question_of', ['number' => $position, 'total' => $questionTotal]) }}</span>
                        {{ $question->prompt }}
                    </legend>

                    @if ($question->type === QuestionType::Essay)
                        <label class="sr-only" for="answer-{{ $question->id }}">{{ __('competitions.student.essay_answer') }}</label>
                        <textarea id="answer-{{ $question->id }}" name="answers[{{ $question->id }}]" rows="6" maxlength="{{ config('competitions.student.essay_max_chars') }}"
                            class="block w-full rounded-xl border border-muted/70 bg-white p-4 text-ink focus:border-primary focus:ring-2 focus:ring-primary/15 focus:outline-none">{{ $current }}</textarea>
                    @else
                        @foreach ($item['options'] as $key)
                            <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-line px-4 py-3 has-checked:border-primary has-checked:bg-primary-soft">
                                <input type="radio" name="answers[{{ $question->id }}]" value="{{ $attempt->optionToken((int) $question->id, $key) }}" class="size-5 shrink-0 accent-primary" @checked($current === $key)>
                                <span class="text-ink">{{ $labels->get($key) }}</span>
                            </label>
                        @endforeach
                    @endif
                </fieldset>
            @endforeach

            @error('answers')
                <x-alert type="error">{{ $message }}</x-alert>
            @enderror

            {{-- «التالي» أول زر في الشيفرة فيكون زر Enter، وموضعه بصرياً بعد «السابق» (order-last) --}}
            <div class="flex flex-wrap justify-between gap-3">
                <x-button name="go" value="next" class="order-last" arrow>{{ $number === $total ? __('competitions.exam.to_review') : __('competitions.exam.next') }}</x-button>
                <x-button variant="outline" name="go" value="prev" :disabled="$number === 1">{{ __('competitions.exam.previous') }}</x-button>
            </div>
        </section>

        <nav aria-label="{{ __('competitions.student.units_list') }}" class="concept-panel p-5">
            <p class="mb-3 text-sm font-semibold text-ink">{{ __('competitions.student.units_list') }}</p>
            <ul class="flex flex-wrap gap-2">
                @foreach ($attempt->layout as $index => $layoutUnit)
                    @php($done = collect($layoutUnit['questions'])->every(fn ($q) => $attempt->answer((int) $q['id']) !== null))
                    <li>
                        <button type="submit" name="go" value="{{ $index + 1 }}"
                            aria-label="{{ __('competitions.student.go_to_unit', ['number' => $index + 1, 'status' => $done ? __('competitions.exam.answered') : __('competitions.exam.unanswered')]) }}"
                            @if ($index + 1 === $number) aria-current="step" @endif
                            @class([
                                'size-10 rounded-full text-sm font-semibold',
                                'bg-primary text-white' => $done,
                                'border border-line bg-white text-ink' => ! $done,
                                'ring-2 ring-accent ring-offset-2' => $index + 1 === $number,
                            ])>{{ $index + 1 }}</button>
                    </li>
                @endforeach
                <li>
                    <button type="submit" name="go" value="review" class="h-10 rounded-full border border-primary px-4 text-sm font-semibold text-primary">{{ __('competitions.exam.to_review') }}</button>
                </li>
            </ul>
        </nav>
    </form>
</x-layouts.account>
