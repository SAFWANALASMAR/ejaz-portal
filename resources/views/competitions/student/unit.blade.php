{{--
    وحدة من اختبار الطالب في صفحة: النص المقروء أو مقطع يوتيوب، ثم أسئلته مرقمة من الإجمالي بخياراتها بترتيب
    الطالب. كل زر (السابقة، والتالية، ورقم وحدة، والمراجعة) يحفظ الإجابات في الخادم ثم ينتقل (SaveAttemptAnswers)،
    وانتهاء العداد يرسل النموذج بالتسليم الآلي. ولا يصل الخيار الصحيح إلى الصفحة (RoundQuestions بلا عموده).
    بوضع التركيز (x-layouts.exam — decisions/2026-11-06-exam-focus-mode.md): أرقام الصفحات عمود ثابت في جهة البداية على الحاسوب
    واللوحي، وشريط أفقي ثابت أعلى الصفحة على الجوال، وكلاهما بعد السؤال في الشيفرة فيبقى «التالي» زر Enter.
--}}
@php
    use App\Enums\PassageKind;
    use App\Enums\QuestionType;

    $questionTotal = count($attempt->questionIds());
@endphp

<x-layouts.exam :competition="$competition" :round="$round" :attempt="$attempt">
    <form method="POST" action="{{ route('competitions.student.save', [$competition->slug, $round->uuid, $number]) }}" data-exam-autosubmit data-exam-form class="exam-layout">
        @csrf
        {{-- يضبطه العداد عند انتهاء الوقت، فيُحفظ ما في الصفحة ثم تُسلَّم المحاولة --}}
        <input type="hidden" name="go" value="next" disabled data-exam-auto-go>
        <input type="hidden" name="auto" value="0">

        <section class="exam-panel concept-panel" aria-labelledby="exam-unit-label">
            {{-- رقم الصفحة في عمود الأرقام ظاهراً، وهنا لقارئ الشاشة وحده، فيبقى السؤال وخياراته وزراه في الشاشة بلا تمرير --}}
            <p id="exam-unit-label" class="sr-only">{{ __('competitions.student.unit_of', ['number' => $number, 'total' => $total]) }}</p>

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
                            class="group flex aspect-video w-full flex-col items-center justify-center gap-3 rounded-xl bg-primary p-6 text-center text-white">
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

                <fieldset class="space-y-2.5">
                    {{-- whitespace-pre-line على نص السؤال وحده: على العنوان كله كانت أسطر الشيفرة تظهر أسطراً فارغة فوق السؤال --}}
                    <legend class="mb-3 leading-8 font-medium text-ink">
                        <span class="block text-sm font-semibold text-accent-dark">{{ __('competitions.exam.question_of', ['number' => $position, 'total' => $questionTotal]) }}</span>
                        <span class="block whitespace-pre-line">{{ $question->prompt }}</span>
                    </legend>

                    @if ($question->type === QuestionType::Essay)
                        <label class="sr-only" for="answer-{{ $question->id }}">{{ __('competitions.student.essay_answer') }}</label>
                        <textarea id="answer-{{ $question->id }}" name="answers[{{ $question->id }}]" rows="6" maxlength="{{ config('competitions.student.essay_max_chars') }}"
                            class="block w-full rounded-xl border border-muted/70 bg-white p-4 text-ink focus:border-primary focus:ring-2 focus:ring-primary/15 focus:outline-none">{{ $current }}</textarea>
                    @else
                        @foreach ($item['options'] as $key)
                            <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-line px-4 py-2.5 has-checked:border-primary has-checked:bg-primary-soft">
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

            {{-- «التالي» أول زر في الشيفرة فيكون زر Enter، وموضعه بصرياً بعد «السابق» (order-last). وبعد الضغط يظهر أن الضغطة
                 وصلت فوراً (data-exam-pending — student-competition.js) --}}
            <div class="flex flex-wrap justify-between gap-3">
                <x-button name="go" value="next" class="order-last" data-exam-step arrow>{{ $number === $total ? __('competitions.exam.to_review') : __('competitions.exam.next') }}</x-button>
                <x-button variant="outline" name="go" value="prev" data-exam-step :disabled="$number === 1">{{ __('competitions.exam.previous') }}</x-button>
            </div>
            <p class="sr-only" role="status" data-exam-saving data-message="{{ __('competitions.student.saving') }}"></p>
            {{-- تعذّر الحفظ في التنقّل داخل الصفحة (ازدحام أو خطأ في الخادم): الإجابات باقية، ولا إعادة إرسال تلقائية (decisions/2026-11-07-exam-fullscreen.md) --}}
            <p class="rounded-xl bg-primary-soft px-4 py-2.5 text-sm font-semibold text-primary-deep" role="alert" data-exam-failed data-message="{{ __('competitions.student.save_failed') }}" hidden></p>
        </section>

        {{-- أرقام الصفحات بطابعها: الدائرة الزرقاء للمجاب، والكهرمانية لما زاره الطالب وتركه بلا إجابة، والبيضاء لما لم يصله،
             والحلقة للحالي، ومفتاح الألوان تحتها. عمود ثابت على الحاسوب واللوحي، وشريط أفقي يُسحب ويتمركز على الحالي على الجوال
             (data-exam-units-list — student-competition.js) --}}
        <nav aria-label="{{ __('competitions.student.units_list') }}" class="exam-units" data-exam-units>
            <ul class="exam-units-list" data-exam-units-list>
                @foreach ($attempt->layout as $index => $layoutUnit)
                    @php
                        $done = collect($layoutUnit['questions'])->every(fn ($q) => $attempt->answer((int) $q['id']) !== null);
                        $current = $index + 1 === $number;
                        $state = $done ? 'answered' : (! $current && in_array($index + 1, $visited, true) ? 'skipped' : 'unvisited');
                        // الحالية بلا إجابة «لم تتم الإجابة»، لا «لم تصلها بعد»
                        $status = $current && ! $done ? __('competitions.exam.unanswered') : __('competitions.student.unit_states.'.$state);
                    @endphp
                    <li>
                        <button type="submit" name="go" value="{{ $index + 1 }}" data-exam-step data-unit-state="{{ $state }}"
                            aria-label="{{ __('competitions.student.go_to_unit', ['number' => $index + 1, 'status' => $status]) }}"
                            @if ($current) aria-current="step" @endif
                            @class([
                                'size-10 shrink-0 rounded-full text-sm font-semibold',
                                'bg-primary text-white' => $state === 'answered',
                                'exam-unit-skipped' => $state === 'skipped',
                                'border border-line bg-white text-ink' => $state === 'unvisited',
                                'ring-2 ring-accent ring-offset-2' => $current,
                            ])>{{ $index + 1 }}</button>
                    </li>
                @endforeach
                <li class="exam-units-review">
                    <button type="submit" name="go" value="review" data-exam-step class="h-10 shrink-0 rounded-full border border-primary bg-white px-4 text-sm font-semibold whitespace-nowrap text-primary">{{ __('competitions.exam.to_review') }}</button>
                </li>
            </ul>

            <ul class="exam-units-legend" aria-label="{{ __('competitions.student.units_legend.label') }}" data-exam-units-legend>
                <li><span class="exam-legend-dot bg-primary" aria-hidden="true"></span>{{ __('competitions.student.units_legend.answered') }}</li>
                <li><span class="exam-legend-dot exam-unit-skipped" aria-hidden="true"></span>{{ __('competitions.student.units_legend.skipped') }}</li>
                <li><span class="exam-legend-dot exam-legend-current" aria-hidden="true"></span>{{ __('competitions.student.units_legend.current') }}</li>
            </ul>
        </nav>
    </form>
</x-layouts.exam>
