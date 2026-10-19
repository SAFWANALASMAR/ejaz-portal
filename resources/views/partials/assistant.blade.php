{{-- المساعد الذكي (requirements/10): زر عائم ونافذة محادثة. يظهر متى شُغّل من اللوحة وحده، ويبقى بمحادثته عبر التنقّل
     السريع (@persist). والتفاعل كله في resources/js/assistant.js بلا سكربت مضمّن، والأجوبة تُكتب نصاً (textContent)
     في قوالب <template> هنا، فلا HTML من الخادم ولا من النموذج --}}
@php
    $assistant = \App\Models\AssistantSettings::current();
@endphp

@if ($assistant->is_enabled)
    @persist('assistant')
        <div data-assistant
            data-ask-url="{{ route('assistant.ask', [], false) }}"
            data-rate-url="{{ url('/assistant/answers') }}"
            data-max="{{ (int) config('assistant.max_question') }}"
            data-label-sending="{{ __('assistant.widget.sending') }}"
            data-label-error="{{ __('assistant.widget.error') }}"
            data-label-thanks="{{ __('assistant.widget.thanks') }}"
            data-label-remaining="{{ __('assistant.widget.remaining') }}">

            <button type="button" data-assistant-toggle aria-expanded="false" aria-controls="assistant-panel"
                class="fixed bottom-4 end-4 z-40 inline-flex items-center gap-2 rounded-full bg-primary px-4 py-3 font-semibold text-white shadow-lg transition hover:bg-primary-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                {{ svg('heroicon-o-chat-bubble-left-right', 'size-6 shrink-0', ['aria-hidden' => 'true']) }}
                <span class="sr-only sm:not-sr-only">{{ __('assistant.widget.open') }}</span>
            </button>

            <section id="assistant-panel" role="dialog" aria-modal="false" aria-labelledby="assistant-title" aria-describedby="assistant-disclaimer" hidden
                class="fixed inset-0 z-50 flex flex-col bg-white sm:inset-auto sm:bottom-20 sm:end-4 sm:h-[min(38rem,calc(100dvh-7rem))] sm:w-[25rem] sm:overflow-hidden sm:rounded-2xl sm:border sm:border-line sm:shadow-2xl">
                <header class="flex items-start gap-3 bg-primary px-4 py-3 text-white">
                    <span class="mt-1 inline-flex size-9 shrink-0 items-center justify-center rounded-full bg-white/15" aria-hidden="true">
                        {{ svg('heroicon-o-sparkles', 'size-5') }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <h2 id="assistant-title" class="text-lg font-bold leading-7">{{ __('assistant.widget.title') }}</h2>
                        <p id="assistant-disclaimer" class="text-sm leading-6 text-white/85">{{ __('assistant.widget.disclaimer') }}</p>
                    </div>
                    <button type="button" data-assistant-close
                        class="inline-flex size-9 shrink-0 items-center justify-center rounded-full hover:bg-white/15 focus-visible:outline-2 focus-visible:outline-white">
                        {{ svg('heroicon-o-x-mark', 'size-6', ['aria-hidden' => 'true']) }}
                        <span class="sr-only">{{ __('assistant.widget.close') }}</span>
                    </button>
                </header>

                <div data-assistant-log role="log" aria-live="polite" aria-relevant="additions" tabindex="-1"
                    class="flex-1 space-y-3 overflow-y-auto overscroll-contain bg-surface px-4 py-4 focus:outline-none">
                    <div class="rounded-2xl rounded-ss-sm bg-white px-4 py-3 text-ink shadow-sm">
                        <p class="whitespace-pre-line">{{ $assistant->welcome_text ?: __('assistant.widget.intro') }}</p>
                    </div>

                    @php
                        $suggestions = \App\Support\Assistant\AssistantWidget::suggestions($assistant);
                    @endphp
                    @if ($suggestions !== [])
                        <div data-assistant-suggestions>
                            <p class="mb-2 text-sm font-semibold text-muted">{{ __('assistant.widget.suggestions') }}</p>
                            <ul class="flex flex-wrap gap-2">
                                @foreach ($suggestions as $suggestion)
                                    <li>
                                        <button type="button" data-assistant-suggestion
                                            class="rounded-full border border-primary/30 bg-white px-3 py-1.5 text-start text-sm text-primary hover:border-primary hover:bg-primary-soft focus-visible:outline-2 focus-visible:outline-primary">{{ $suggestion }}</button>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>

                <form data-assistant-form class="border-t border-line bg-white px-3 pb-3 pt-2" novalidate>
                    <p id="assistant-privacy" class="mb-2 flex items-start gap-1.5 text-xs leading-5 text-muted">
                        {{ svg('heroicon-o-shield-exclamation', 'mt-0.5 size-4 shrink-0 text-accent', ['aria-hidden' => 'true']) }}
                        <span>{{ __('assistant.widget.privacy') }}</span>
                    </p>
                    <label for="assistant-question" class="sr-only">{{ __('assistant.widget.question') }}</label>
                    <div class="flex items-end gap-2">
                        <textarea id="assistant-question" name="question" rows="2" required
                            maxlength="{{ (int) config('assistant.max_question') }}"
                            aria-describedby="assistant-privacy assistant-remaining"
                            placeholder="{{ __('assistant.widget.placeholder') }}"
                            class="block min-h-12 w-full resize-none rounded-xl border border-muted/70 bg-white px-3 py-2 text-ink focus:border-primary focus:ring-2 focus:ring-primary/15 focus:outline-none"></textarea>
                        <button type="submit" data-assistant-send
                            class="inline-flex size-12 shrink-0 items-center justify-center rounded-full bg-primary text-white transition hover:bg-primary-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary disabled:cursor-not-allowed disabled:opacity-60">
                            {{ svg('heroicon-o-paper-airplane', 'size-5 -scale-x-100', ['aria-hidden' => 'true']) }}
                            <span class="sr-only">{{ __('assistant.widget.send') }}</span>
                        </button>
                    </div>
                    <p id="assistant-remaining" data-assistant-remaining class="mt-1 text-xs text-muted" aria-live="polite"></p>
                </form>

                {{-- قالبا الرسالتين: سؤال الزائر، وجواب المساعد بمصادره وأزراره --}}
                <template data-assistant-template="question">
                    <div class="ms-8 rounded-2xl rounded-se-sm bg-primary px-4 py-3 text-white">
                        <p class="sr-only">{{ __('assistant.widget.you') }}:</p>
                        <p data-text class="whitespace-pre-line break-words"></p>
                    </div>
                </template>

                <template data-assistant-template="answer">
                    <div class="me-4 rounded-2xl rounded-ss-sm bg-white px-4 py-3 text-ink shadow-sm">
                        <p class="sr-only">{{ __('assistant.name') }}:</p>
                        <p data-text class="whitespace-pre-line break-words"></p>

                        <div data-sources hidden class="mt-3 border-t border-line pt-2">
                            <p class="text-sm font-semibold text-muted">{{ __('assistant.widget.sources') }}</p>
                            <ol data-source-list class="mt-1 list-inside list-decimal space-y-1 text-sm"></ol>
                        </div>

                        <div data-go hidden class="mt-3 flex flex-wrap gap-2"></div>

                        <p data-contact hidden class="mt-3">
                            <a href="{{ route('contact') }}" @navigate(route('contact'))
                                class="inline-flex items-center gap-1 rounded-full border border-primary px-3 py-1.5 text-sm font-semibold text-primary hover:bg-primary-soft">
                                {{ svg('heroicon-o-envelope', 'size-4', ['aria-hidden' => 'true']) }}
                                {{ __('assistant.widget.contact') }}
                            </a>
                        </p>

                        <div data-rating hidden class="mt-3 flex items-center gap-2 text-sm">
                            <button type="button" data-rate="helpful" aria-pressed="false"
                                class="inline-flex items-center gap-1 rounded-full border border-line px-3 py-1 text-muted hover:border-secondary hover:text-secondary aria-pressed:border-secondary aria-pressed:bg-secondary-soft aria-pressed:text-secondary">
                                {{ svg('heroicon-o-hand-thumb-up', 'size-4', ['aria-hidden' => 'true']) }}
                                {{ __('assistant.widget.helpful') }}
                            </button>
                            <button type="button" data-rate="unhelpful" aria-pressed="false"
                                class="inline-flex items-center gap-1 rounded-full border border-line px-3 py-1 text-muted hover:border-red-700 hover:text-red-700 aria-pressed:border-red-700 aria-pressed:bg-red-50 aria-pressed:text-red-700">
                                {{ svg('heroicon-o-hand-thumb-down', 'size-4', ['aria-hidden' => 'true']) }}
                                {{ __('assistant.widget.unhelpful') }}
                            </button>
                            <span data-rated class="text-secondary" role="status"></span>
                        </div>
                    </div>
                </template>

                <template data-assistant-template="go">
                    <a class="inline-flex items-center gap-1 rounded-full bg-primary px-3 py-1.5 text-sm font-semibold text-white hover:bg-primary-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                        <span data-label>{{ __('assistant.widget.go') }}</span>
                        {{ svg('heroicon-o-arrow-left', 'size-4 shrink-0', ['aria-hidden' => 'true']) }}
                    </a>
                </template>

                <template data-assistant-template="go-label">{{ __('assistant.widget.go_to', ['title' => '__TITLE__']) }}</template>
            </section>
        </div>
    @endpersist
@endif
