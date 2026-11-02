{{--
    لوح برنامج المكتبة في صفحته (docs/decisions/2026-11-02-reading-program.md): العنصر المختار من المكتبة، ثم للمسجل المؤكد: زر البدء
    (القارئ للكتاب، وصفحة العنصر للصوت والمرئي)، وتقدّمه بشريط من سجل «قراءاتي»، و«أتممت» بعد بلوغ النهاية بقواعد الخادم، ثم شهادة
    الإتمام. ومن أوقف حفظ قراءاته يُنبَّه أن البرنامج يحتاجه. يُرسم مع كل طلب (الصفحة لا تُخزَّن لمسجّل الدخول).
--}}
@props(['program', 'registration'])

@php
    use App\Enums\RegistrationStatus;

    $kind = $program->libraryKind();
    $item = $program->libraryItem;
    $confirmed = $registration?->status === RegistrationStatus::Confirmed;
    $state = $confirmed ? \App\Support\LibraryProgramProgress::for($program, auth()->user(), $registration) : null;
    $trackingOff = auth()->user()?->reading_tracking_disabled_at !== null;
    $start = $kind === \App\Enums\LibraryProgramKind::Reading
        ? route('programs.read', $program->slug)
        : route('library.show', [$item->section->value, $item->slug]);
@endphp

<x-card data-library-program="{{ $kind->value }}">
    <h2 class="flex items-center gap-2 text-lg font-black text-primary-dark"><span class="heading-flank-dot" aria-hidden="true"></span>{{ $kind->getLabel() }}</h2>
    <p class="mt-2 font-semibold text-ink">{{ $item->title }}</p>
    @if ($item->duration_seconds)
        <p class="mt-1 text-sm text-muted">{{ __('programs.library.duration', ['minutes' => number_format((int) ceil($item->duration_seconds / 60))]) }}</p>
    @elseif ($item->page_count)
        <p class="mt-1 text-sm text-muted">{{ __('programs.library.pages', ['count' => number_format($item->page_count)]) }}</p>
    @endif

    {{-- عنصر أُلغي نشره أو حُذف: لا بدء ولا إتمام --}}
    @if ($confirmed && $item->is_published && ! $item->trashed())
        @if ($registration->completed_at)
            <p class="mt-4 font-semibold text-primary" data-library-program-done>{{ __('programs.library.done') }}</p>
            @if ($registration->certificate_path)
                <a href="{{ route('account.programs.certificate', $registration) }}" class="mt-2 inline-flex items-center gap-1 text-primary underline decoration-accent underline-offset-4">
                    {{ \App\Support\Icon::svg('arrow-down-tray', 'size-4') }}
                    {{ __('programs.library.certificate') }}
                </a>
            @else
                <p class="mt-2 text-sm text-muted">{{ __('programs.library.certificate_pending') }}</p>
            @endif
        @else
            @if ($trackingOff)
                <p class="mt-4 rounded-xl bg-accent-light/60 p-3 text-sm text-primary-dark" data-library-program-tracking-off>
                    {{ __('programs.library.tracking_off') }}
                    <a href="{{ route('account.profile') }}#reading-tracking" class="font-semibold underline decoration-accent underline-offset-4">{{ __('reading.tracking.settings') }}</a>
                </p>
            @endif

            <progress max="100" value="{{ $state->percent() }}" aria-label="{{ __('programs.library.progress', ['title' => $item->title]) }}" data-library-program-progress
                class="mt-4 block h-2.5 w-full appearance-none overflow-hidden rounded-full bg-primary-soft text-primary forced-colors:appearance-auto [&::-moz-progress-bar]:rounded-full [&::-moz-progress-bar]:bg-current [&::-webkit-progress-bar]:bg-primary-soft [&::-webkit-progress-value]:rounded-full [&::-webkit-progress-value]:bg-current">{{ $state->percent() }}%</progress>
            <p class="mt-1 text-sm text-muted">{{ __('programs.library.percent', ['percent' => $state->percent()]) }}</p>

            <div class="mt-4 grid gap-2">
                <x-button class="w-full" :href="$start" arrow>{{ $kind->startLabel() }}</x-button>
                <form method="POST" action="{{ route('account.programs.complete', $registration) }}">
                    @csrf
                    <x-button variant="outline" class="w-full" :disabled="! $state->complete()">{{ $kind->completeLabel() }}</x-button>
                </form>
                @unless ($state->complete())
                    <p class="text-sm text-muted">{{ __('programs.library.complete_hint.'.$kind->value) }}</p>
                @endunless
            </div>
        @endif
    @endif
</x-card>
