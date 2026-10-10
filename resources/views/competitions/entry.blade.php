{{--
    نموذج المشاركة في لوحة المستخدم (الصورة 04): الفئة والمنطقة، ثم الأسئلة بترتيب المتقدم، ثم حفظ
    المسودة والإرسال. ويحفظ السكربت المسودة تلقائياً، ويرفع المقاطع مجزأة (competition-entry.js).
    وبعد الإرسال أو الإغلاق يُعرض ملخص المشاركة للقراءة فقط.
--}}
@php
    use App\Enums\QuestionType;
    use App\Enums\SaudiRegion;
    use App\Support\Dates;

    $videos = $submission->videos->keyBy('question_id');
@endphp

<x-layouts.account :title="__('competitions.entry.title', ['competition' => $competition->title])">
    @if ($submission->reference)
        <div class="rounded-2xl border border-line/70 bg-white p-6 shadow-xs">
            <dl class="grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-sm text-muted">{{ __('competitions.entry.reference') }}</dt>
                    <dd class="mt-1 text-2xl font-bold tracking-widest text-primary" dir="ltr">{{ $submission->reference }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-muted">{{ __('competitions.entry.submitted_at') }}</dt>
                    <dd class="mt-1 font-medium text-ink">{{ Dates::display($submission->submitted_at, withTime: true) }}</dd>
                </div>
            </dl>
        </div>
    @endif

    @if ($editable)
        <x-alert type="info">
            {{ __('competitions.entry.intro') }}
            <span class="mt-1 block">{{ __('competitions.entry.anonymous') }}</span>
            <span class="mt-1 block font-semibold">{{ __('competitions.entry.closes', ['date' => Dates::display($competition->closes_at, withTime: true)]) }}</span>
        </x-alert>
    @elseif (! $submission->reference)
        <x-alert type="warning">{{ __('competitions.entry.closed') }}</x-alert>
    @endif

    <form method="POST" action="{{ route('competitions.entry.save', $competition->slug) }}" class="space-y-6"
        @if ($editable) data-autosave="{{ route('competitions.entry.autosave', $competition->slug) }}" @endif>
        @csrf

        <fieldset class="grid gap-5 rounded-2xl border border-line/70 bg-white p-6 shadow-xs sm:grid-cols-2" @disabled(! $editable)>
            @if ($competition->categoryList() !== [])
                <x-field name="category" type="select" :label="__('competitions.entry.category')" required>
                    <option value="">{{ __('competitions.entry.choose') }}</option>
                    @foreach ($competition->categoryList() as $category)
                        <option value="{{ $category }}" @selected(old('category', $submission->category) === $category)>{{ $category }}</option>
                    @endforeach
                </x-field>
            @endif

            <x-field name="region" type="select" :label="__('competitions.entry.region')" required>
                <option value="">{{ __('competitions.entry.choose') }}</option>
                @foreach (SaudiRegion::cases() as $region)
                    <option value="{{ $region->value }}" @selected(old('region', $submission->region?->value) === $region->value)>{{ $region->getLabel() }}</option>
                @endforeach
            </x-field>
        </fieldset>

        @foreach ($submission->orderedQuestions() as $question)
            @php($error = $errors->first('answers.'.$question->id))
            <fieldset class="rounded-2xl border border-line/70 bg-white p-6 shadow-xs" @disabled(! $editable)
                @if ($error) aria-describedby="answer-{{ $question->id }}-error" @endif>
                <legend class="sr-only">{{ __('competitions.entry.question', ['number' => $loop->iteration]) }}</legend>
                <p class="text-sm font-semibold text-accent-dark">
                    {{ __('competitions.entry.question', ['number' => $loop->iteration]) }}
                    @unless ($question->is_required)
                        <span class="text-muted">— {{ __('competitions.entry.optional') }}</span>
                    @endunless
                </p>
                <p class="mt-2 leading-8 font-medium whitespace-pre-line text-ink">{{ $question->prompt }}</p>

                @if ($question->type === QuestionType::Text)
                    <label for="answer-{{ $question->id }}" class="sr-only">{{ __('competitions.entry.answer') }}</label>
                    <textarea id="answer-{{ $question->id }}" name="answers[{{ $question->id }}]" rows="5" maxlength="5000"
                        class="mt-4 block w-full rounded-xl border border-line bg-white px-4 py-3 text-ink focus:border-primary focus:ring-2 focus:ring-primary/15 focus:outline-none">{{ old('answers.'.$question->id, $submission->answer($question)) }}</textarea>
                @elseif ($question->type === QuestionType::MultipleChoice)
                    <div class="mt-4 space-y-2">
                        @foreach ($submission->orderedOptions($question) as $option)
                            <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-line px-4 py-3 has-checked:border-primary has-checked:bg-primary-soft">
                                <input type="radio" name="answers[{{ $question->id }}]" value="{{ $option['key'] }}" class="accent-primary"
                                    @checked(old('answers.'.$question->id, $submission->answer($question)) === $option['key'])>
                                <span class="text-ink">{{ $option['label'] }}</span>
                            </label>
                        @endforeach
                    </div>
                @else
                    @php($video = $videos->get($question->id))
                    <div class="mt-4 space-y-3 rounded-xl bg-surface p-4"
                        data-video-upload="{{ route('competitions.videos.start', [$competition->slug, $question->id]) }}"
                        data-status-url="{{ route('competitions.videos.status', ['video' => '__VIDEO__']) }}"
                        data-complete-url="{{ route('competitions.videos.complete', ['video' => '__VIDEO__']) }}"
                        data-max-bytes="{{ (int) $question->max_video_mb * 1024 * 1024 }}"
                        data-max-seconds="{{ (int) $question->max_video_seconds }}"
                        data-messages="{{ json_encode([
                            'wrong_type' => __('competitions.video.wrong_type'),
                            'too_large' => __('competitions.video.too_large', ['mb' => $question->max_video_mb]),
                            'too_long' => __('competitions.video.too_long', ['seconds' => $question->max_video_seconds]),
                            'progress' => __('competitions.video.progress', ['percent' => ':percent']),
                            'retry' => __('competitions.video.retry'),
                        ]) }}">
                        <p class="text-sm text-muted">{{ __('competitions.video.limits', ['mb' => $question->max_video_mb, 'seconds' => $question->max_video_seconds]) }}</p>
                        <p class="text-sm font-medium text-ink" data-video-status aria-live="polite">
                            @if ($video)
                                {{ __('competitions.video.current', ['status' => $video->status->getLabel()]) }}
                                @if ($video->rejection_reason)
                                    — {{ __('competitions.video.reasons.'.$video->rejection_reason) }}
                                @endif
                            @endif
                        </p>
                        @if ($editable)
                            <label class="block">
                                <span class="text-sm font-semibold text-ink">{{ $video ? __('competitions.video.replace') : __('competitions.video.choose') }}</span>
                                <input type="file" accept="video/mp4" data-video-input class="mt-2 block w-full text-sm text-ink file:me-4 file:rounded-full file:border-0 file:bg-primary file:px-4 file:py-2 file:font-semibold file:text-white">
                            </label>
                            <progress max="100" value="0" class="w-full accent-primary" data-video-progress hidden></progress>
                        @endif
                    </div>
                @endif

                @if ($error)
                    <p id="answer-{{ $question->id }}-error" class="mt-3 text-sm font-medium text-red-700">{{ $error }}</p>
                @endif
            </fieldset>
        @endforeach

        @if ($editable)
            <div class="flex flex-col gap-3 rounded-2xl border border-line/70 bg-white p-5 shadow-xs sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-muted" data-autosave-status aria-live="polite"
                    data-saving="{{ __('competitions.entry.autosave_saving') }}"
                    data-saved="{{ __('competitions.entry.autosave_saved') }}"
                    data-failed="{{ __('competitions.entry.autosave_failed') }}">{{ __('competitions.entry.autosave_idle') }}</p>
                <div class="flex flex-wrap gap-3">
                    <x-button variant="outline">{{ __('competitions.entry.save') }}</x-button>
                    <x-button formaction="{{ route('competitions.entry.submit', $competition->slug) }}" data-confirm="{{ __('competitions.entry.submit_confirm') }}" arrow>
                        {{ __('competitions.entry.submit') }}
                    </x-button>
                </div>
            </div>
        @endif
    </form>
</x-layouts.account>
