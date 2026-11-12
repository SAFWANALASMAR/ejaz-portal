{{--
    بطاقة مسابقة في صفحة المسابقات (Final-Competitions): بطاقة ملونة تتعاقب درجتها، بمربع الأيقونة وشارة حالتها («قريباً» للقادمة،
    و«منتهية» للسابقة وبجانبها «النتائج معتمدة» متى اعتُمدت)، ثم العنوان والوصف والموعد، ثم «التفاصيل». ولا زر تسجيل في البطاقة،
    فالتسجيل في صفحة المسابقة وحدها بحالاتها.
--}}
@php
    use App\Enums\CompetitionStatus;
    use App\Support\Dates;
@endphp

<article class="tone-card flex h-full flex-col gap-2.5 p-5" data-competition-card="{{ $status->value }}">
    <div class="flex flex-wrap items-center gap-2">
        <x-icon-tile :icon="$status === CompetitionStatus::Closed ? 'archive-box' : 'trophy'" size="sm" />
        <span class="tone-chip">{{ $status->getLabel() }}</span>
        @if ($status === CompetitionStatus::Closed && $competition->resultsApproved())
            <span class="tone-chip" data-results-approved>{{ __('ui.inner.competitions.results') }}</span>
        @endif
    </div>
    <h3 class="line-clamp-2 text-xl leading-snug font-black">{{ $competition->title }}</h3>
    @if (filled($competition->description))
        <p class="tone-card-muted line-clamp-3 text-sm leading-6">{{ $competition->description }}</p>
    @endif
    <p class="tone-card-muted mt-1 inline-flex items-center gap-1.5 text-sm">
        {{ \App\Support\Icon::svg('calendar', 'size-4 shrink-0', ['aria-hidden' => 'true']) }}
        @if ($competition->schedule_pending)
            {{ __('competitions.list.schedule_pending') }}
        @elseif ($status === CompetitionStatus::Upcoming)
            {{ __('competitions.list.opens', ['date' => Dates::display($competition->opens_at)]) }}
        @elseif ($status === CompetitionStatus::Open)
            {{ __('competitions.list.closes', ['date' => Dates::display($competition->closes_at)]) }}
        @else
            {{ __('competitions.list.closed_on', ['date' => Dates::display($competition->closes_at)]) }}
        @endif
    </p>
    <a href="{{ route('competitions.show', $competition->slug) }}" wire:navigate class="tone-pill mt-auto self-start">
        {{ __('competitions.list.details') }}<span class="sr-only">: {{ $competition->title }}</span>
    </a>
</article>
