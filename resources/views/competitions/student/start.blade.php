{{--
    صفحة المرحلة قبل البدء: النافذة، وعدد الأسئلة، والمدة، وتعليمات قصيرة، وزر «ابدأ الاختبار» في النافذة وحدها.
    والبدء ينشئ المحاولة مرة واحدة ويبدأ العداد (StartAttempt).
--}}
@php
    use App\Support\Dates;

    $running = $round->isRunning();
@endphp

<x-layouts.account :title="__('competitions.student.round_title', ['round' => $round->name, 'competition' => $competition->title])">

    <section class="space-y-5 rounded-2xl border border-line/70 bg-white p-6 shadow-xs">
        <h2 class="text-xl font-bold text-primary">{{ $round->name }}</h2>
        @if ($round->branch)
            <p class="font-semibold text-accent">{{ $round->branch }}</p>
        @endif

        <dl class="grid gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-sm text-muted">{{ __('competitions.student.window') }}</dt>
                <dd class="mt-1 font-medium text-ink">{{ Dates::display($round->starts_at, withTime: true) }} — {{ Dates::display($round->ends_at, withTime: true) }}</dd>
            </div>
            <div>
                <dt class="text-sm text-muted">{{ __('competitions.student.questions_and_duration') }}</dt>
                <dd class="mt-1 font-medium text-ink">{{ trans_choice('competitions.student.questions_count', $round->question_count, ['count' => $round->question_count]) }} · {{ trans_choice('competitions.student.minutes', $round->duration_minutes, ['count' => $round->duration_minutes]) }}</dd>
            </div>
        </dl>

        {{-- تنبيهات دليل الدخول من نصوص المسابقة (المطوية) إن كُتبت، وإلا التعليمات العامة بوسم «مثال تجريبي» --}}
        @php($alerts = $competition->pageList('guide_alerts'))
        @if ($alerts !== [])
            <p class="font-semibold text-ink">{{ __('competitions.student.page.guide_alerts') }}</p>
            <ul class="list-disc space-y-2 ps-5 leading-7 text-ink">
                @foreach ($alerts as $line)
                    <li>{{ $line }}</li>
                @endforeach
            </ul>
        @else
            <p class="flex items-center gap-2 font-semibold text-ink">{{ __('competitions.student.instructions_heading') }} <x-sample /></p>
            <ul class="list-disc space-y-2 ps-5 leading-7 text-ink">
                @foreach (__('competitions.student.instructions') as $line)
                    <li>{{ $line }}</li>
                @endforeach
            </ul>
        @endif

        @error('round')
            <x-alert type="error">{{ $message }}</x-alert>
        @enderror

        @if (! $eligible)
            <x-alert type="warning">{{ __('competitions.student.not_qualified') }}</x-alert>
        @elseif ($running)
            <form method="POST" action="{{ route('competitions.student.start', [$competition->slug, $round->uuid]) }}" data-once>
                @csrf
                <x-button class="w-full sm:w-auto" arrow>{{ __('competitions.student.start') }}</x-button>
            </form>
        @elseif ($round->starts_at->isFuture())
            <x-alert type="info">{{ __('competitions.student.not_yet', ['date' => Dates::display($round->starts_at, withTime: true)]) }}</x-alert>
        @else
            <x-alert type="info">{{ __('competitions.student.round_closed') }}</x-alert>
        @endif
    </section>
</x-layouts.account>
