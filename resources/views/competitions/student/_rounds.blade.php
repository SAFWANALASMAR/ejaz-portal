{{--
    مراحل مسابقة الطلاب: المقدمة من نصوص الصفحة، ولكل مرحلة اسمها ومجالها وحالتها، وللطالب المسجل زر الدخول في نافذتها
    وحدها. بلا عدد الأسئلة ولا المدة (في صفحة بدء الاختبار)، والتواريخ بخيار «إظهار مواعيد المراحل» وحده، والنوافذ
    تعمل بها في الحالين (ملاحظة المدير التنفيذي، 29 أكتوبر 2026).
--}}
@php
    use App\Support\CompetitionPage;
    use App\Support\Dates;
    use Illuminate\Support\Facades\Storage;
    use Illuminate\Support\Number;

    $attempts = $submission?->attempts()->get()->keyBy('round_id') ?? collect();
@endphp

@if ($rounds->isNotEmpty())
    <div data-rounds>
        <x-section-heading>{{ __('competitions.student.rounds') }}</x-section-heading>
        @if ($intro = $competition->pageText(CompetitionPage::ROUNDS_INTRO))
            <p class="measure mt-3 leading-8 text-ink">{{ $intro }}</p>
        @endif
        @if ($submission && $attempts->isNotEmpty())
            <p class="mt-1 text-sm font-semibold text-primary">{{ __('competitions.student.page.branches_done', ['done' => $attempts->filter(fn ($attempt) => ! $attempt->isInProgress())->count(), 'total' => $rounds->count()]) }}</p>
        @endif
        <ol class="mt-4 space-y-3">
            @foreach ($rounds as $round)
                @php
                    $attempt = $attempts->get($round->id);
                    $domain = $round->branch ? $domains->search($round->branch) : false;
                @endphp
                <li class="tone-card group flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex gap-4">
                        @if ($round->image_path)
                            <img src="{{ Storage::disk('public')->url($round->image_path) }}" alt="" class="size-16 shrink-0 rounded-xl object-cover" loading="lazy">
                        @else
                            <span class="relative shrink-0" aria-hidden="true">
                                <x-icon-tile icon="flag" />
                                <span class="keep-color absolute -top-1.5 -start-1.5 grid size-6 place-items-center rounded-full bg-primary text-xs font-bold text-white ring-2 ring-white">{{ Number::format($loop->iteration) }}</span>
                            </span>
                        @endif
                        <div class="space-y-1">
                            <h3 class="text-lg font-black">{{ $round->name }}</h3>
                            @if ($domain !== false)
                                <p class="tone-card-muted text-sm font-semibold">{{ __('competitions.student.page.domain', ['ordinal' => __('competitions.student.page.places')[$domain + 1] ?? Number::format($domain + 1)]) }}: {{ $round->branch }}</p>
                            @endif
                            @if ($competition->show_round_dates)
                                <p class="tone-card-muted flex items-center gap-2 text-sm" data-round-dates>
                                    {{ \App\Support\Icon::svg('calendar', 'size-4 shrink-0', ['aria-hidden' => 'true']) }}
                                    {{ Dates::display($round->starts_at, withTime: true) }} — {{ Dates::display($round->ends_at, withTime: true) }}
                                </p>
                            @endif
                        </div>
                    </div>

                    @if ($submission && $attempt && ! $attempt->isInProgress())
                        <a href="{{ route('competitions.student.status', [$competition->slug, $round->uuid]) }}" class="tone-pill">{{ __('competitions.student.received_short') }}</a>
                    @elseif ($submission && $round->isRunning())
                        <x-button :href="route('competitions.student.round', [$competition->slug, $round->uuid])" size="sm" arrow>
                            {{ $attempt ? __('competitions.student.resume') : __('competitions.student.enter') }}
                        </x-button>
                    @elseif ($round->isRunning())
                        <span class="keep-color w-fit rounded-full bg-secondary-soft px-3 py-1 text-sm font-semibold text-secondary">{{ __('competitions.student.running') }}</span>
                    @elseif ($round->starts_at->isFuture())
                        <span class="keep-color w-fit rounded-full bg-surface px-3 py-1 text-sm text-muted">{{ __('competitions.student.upcoming') }}</span>
                    @else
                        <span class="keep-color w-fit rounded-full bg-surface px-3 py-1 text-sm text-muted">{{ __('competitions.student.ended') }}</span>
                    @endif
                </li>
            @endforeach
        </ol>
    </div>
@endif
