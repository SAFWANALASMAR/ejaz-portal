{{--
    استبيان قياس الرضا برابط رمزه: بطاقة في المنتصف كصفحة الدخول (الصورة 04)، والأسئلة بأنواعها:
    مقياس من 1 إلى 5، واختيار، ونص. وبعد الإجابة أو عند الإغلاق رسالة بدل النموذج.
--}}
@php
    use App\Actions\Surveys\SubmitSurveyResponse;
    use App\Enums\SurveyQuestionType;
@endphp

<x-layouts.auth :title="$survey->title" :intro="$survey->description ?: __('surveys.public.intro')" :image="false">
    @if ($state === 'unavailable')
        <x-alert type="warning">{{ __('surveys.public.unavailable') }}</x-alert>
    @elseif ($state === 'not_audience')
        <x-alert type="info">{{ __('surveys.public.not_audience') }}</x-alert>
    @elseif ($state === 'answered')
        @unless (session('status'))
            <x-alert type="info">{{ __('surveys.public.already') }}</x-alert>
        @endunless
    @else
        <form method="POST" action="{{ route('surveys.submit', $survey->token) }}" class="space-y-6">
            @csrf

            @foreach ($survey->questions as $question)
                @php($error = $errors->first('answers.'.$question->id))
                <fieldset class="space-y-3" @if ($error) aria-describedby="answer-{{ $question->id }}-error" @endif>
                    <legend class="font-semibold text-ink">
                        <span class="block text-sm text-gold-dark">
                            {{ __('surveys.public.question', ['number' => $loop->iteration]) }}
                            @unless ($question->is_required)
                                <span class="text-muted">— {{ __('surveys.public.optional') }}</span>
                            @endunless
                        </span>
                        {{ $question->prompt }}
                    </legend>

                    @if ($question->type === SurveyQuestionType::Scale)
                        <div class="flex items-center justify-between gap-2">
                            @foreach (range(1, 5) as $value)
                                <label class="flex size-11 cursor-pointer items-center justify-center rounded-full border border-line font-semibold text-ink has-checked:border-primary has-checked:bg-primary has-checked:text-white">
                                    <input type="radio" name="answers[{{ $question->id }}]" value="{{ $value }}" class="sr-only" @checked((string) old('answers.'.$question->id) === (string) $value)>
                                    {{ $value }}
                                </label>
                            @endforeach
                        </div>
                        <div class="flex justify-between text-xs text-muted">
                            <span>{{ __('surveys.public.scale_low') }}</span>
                            <span>{{ __('surveys.public.scale_high') }}</span>
                        </div>
                    @elseif ($question->type === SurveyQuestionType::Choice)
                        @foreach ($question->optionList() as $index => $option)
                            <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-line px-4 py-3 has-checked:border-primary has-checked:bg-primary-soft">
                                <input type="radio" name="answers[{{ $question->id }}]" value="{{ $index }}" class="accent-primary" @checked((string) old('answers.'.$question->id) === (string) $index)>
                                <span class="text-ink">{{ $option }}</span>
                            </label>
                        @endforeach
                    @else
                        <label for="answer-{{ $question->id }}" class="sr-only">{{ $question->prompt }}</label>
                        <textarea id="answer-{{ $question->id }}" name="answers[{{ $question->id }}]" rows="4" maxlength="{{ SubmitSurveyResponse::maxText($survey) }}"
                            class="block w-full rounded-xl border border-line bg-white px-4 py-3 text-ink focus:border-primary focus:ring-2 focus:ring-primary/15 focus:outline-none">{{ old('answers.'.$question->id) }}</textarea>
                    @endif

                    @if ($error)
                        <p id="answer-{{ $question->id }}-error" class="text-sm font-medium text-red-700">{{ $error }}</p>
                    @endif
                </fieldset>
            @endforeach

            {{-- إشعار عند نقطة الجمع: الجملة دائماً، والرابط متى وُجدت صفحة الخصوصية --}}
            <p class="text-sm text-muted">
                {{ auth()->check() ? __('surveys.public.privacy') : __('surveys.public.privacy_guest') }}
                @if (Route::has('privacy'))
                    <a href="{{ route('privacy') }}" target="_blank" class="text-primary underline">{{ __('surveys.public.privacy_link') }}</a>
                @endif
            </p>

            {{-- التحدي ضد الآليات لغير المسجل (Turnstile)، ويتحقق الخادم من رمزه --}}
            @if ($challengeKey ?? null)
                <div class="cf-turnstile" data-sitekey="{{ $challengeKey }}" data-language="ar"></div>
                <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer nonce="{{ Vite::cspNonce() }}"></script>
            @endif

            <x-button class="w-full" size="lg">{{ __('surveys.public.submit') }}</x-button>
        </form>
    @endif
</x-layouts.auth>
