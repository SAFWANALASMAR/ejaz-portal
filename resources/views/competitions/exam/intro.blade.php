{{--
    ثانيًا: الفيديو التعريفي الإلزامي. الزر معطّل حتى انتهاء المقطع (حدث ended في مشغّل HTML5)، والتقديم
    إلى الأمام ممنوع في المشغّل (competition-exam.js). والخادم يسجل بدء المشاهدة، ولا يقبل الانتهاء قبل
    مرور مدة المقطع (FinishExamIntro).
--}}
<x-layouts.account :title="__('competitions.exam.title', ['competition' => $competition->title])">
    @include('competitions.exam._steps', ['current' => 'intro'])

    <section class="space-y-5 concept-panel p-6">
        <h2 class="flex items-center gap-2 text-xl font-black text-primary-dark"><span class="heading-flank-dot" aria-hidden="true"></span>{{ __('competitions.exam.intro_heading') }}</h2>

        @if (blank($competition->intro_video_path))
            <x-alert type="warning">{{ __('competitions.exam.intro_missing') }}</x-alert>
        @else
            <p class="text-muted">{{ __('competitions.exam.intro_text') }}</p>
            <video data-exam-intro="{{ route('competitions.exam.intro.start', $competition->slug) }}"
                src="{{ route('competitions.exam.intro.video', $competition->slug) }}"
                controls controlslist="nodownload noplaybackrate" disablepictureinpicture playsinline preload="metadata"
                class="aspect-video w-full rounded-xl bg-black"></video>
            <noscript><x-alert type="warning">{{ __('competitions.exam.intro_needs_js') }}</x-alert></noscript>

            @error('intro')
                <x-alert type="error">{{ $message }}</x-alert>
            @enderror

            <form method="POST" action="{{ route('competitions.exam.intro.finish', $competition->slug) }}" data-once>
                @csrf
                <x-button data-exam-intro-next disabled arrow>{{ __('competitions.exam.to_instructions') }}</x-button>
            </form>
        @endif
    </section>
</x-layouts.account>
