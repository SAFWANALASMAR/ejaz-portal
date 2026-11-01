{{--
    صفحة المسابقات (Final-Competitions — البند R15، المرحلة ج): من حالة كل مسابقة في اللوحة (Competition::status) بلا بيانات جديدة:
    - «المسابقة الحالية»: المفتوحة، والأولى منها بطاقة بارزة بتدرج داكن، وجوائز مسابقة الطلاب تعدّ تصاعدياً (prize_table).
    - «مسابقات قادمة»: بطاقات ملونة بشارة «قريباً» وموعد الفتح، بلا زر تسجيل.
    - «مسابقات سابقة»: أرشيف ببطاقات ملونة، وشارة «النتائج معتمدة» متى اعتُمدت نتائجها، والتفاصيل في صفحتها.
    والصفحات بترتيب موعد الفتح كما كانت، والتصنيف داخل الصفحة المعروضة.
--}}
@php
    use App\Enums\CompetitionStatus;
    use App\Support\CompetitionPage;
    use App\Support\Dates;
    use App\Support\HomeShowcase;

    $items = collect($competitions->items());
    $open = $items->filter(fn ($competition) => $competition->status() === CompetitionStatus::Open)->values();
    $upcoming = $items->filter(fn ($competition) => $competition->status() === CompetitionStatus::Upcoming)->values();
    $past = $items->filter(fn ($competition) => $competition->status() === CompetitionStatus::Closed)->values();
    $current = $open->first();
    $prizeTotal = $current?->student_mode ? HomeShowcase::prizeTotal($current) : 0;
    $season = $current?->student_mode ? $current->pageText(CompetitionPage::SEASON) : null;
@endphp

<x-layouts.app :title="__('competitions.title')">
    <x-page-hero :title="__('competitions.list.heading')" />

    @if ($items->isEmpty())
        <section class="container-page py-10">
            <x-empty-state icon="trophy" :message="__('competitions.list.empty')" />
        </section>
    @else
        @if ($current)
            <section class="mx-auto max-w-[82.5rem] px-4 pt-8 pb-4 sm:px-7" aria-labelledby="competitions-current" data-competitions-current>
                <x-section-heading id="competitions-current" icon="trophy" class="mb-5">{{ __('ui.inner.competitions.current') }}</x-section-heading>

                <article class="tone-card grid items-center gap-6 p-6 sm:p-8 lg:grid-cols-[1.5fr_1fr]" data-tone="dark">
                    <div class="flex flex-col gap-3">
                        <span class="flex flex-wrap gap-2">
                            <span class="tone-chip">{{ CompetitionStatus::Open->getLabel() }}</span>
                            @if (filled($season))
                                <span class="tone-chip">{{ $season }}</span>
                            @endif
                        </span>
                        <h3 class="text-[1.75rem] leading-tight font-black sm:text-[2.25rem]">{{ $current->title }}</h3>
                        @if (filled($current->description))
                            <p class="tone-card-muted line-clamp-3 text-lg leading-8">{{ $current->description }}</p>
                        @endif
                        <p class="tone-card-muted inline-flex items-center gap-1.5 text-sm">
                            {{ \App\Support\Icon::svg('calendar', 'size-4 shrink-0', ['aria-hidden' => 'true']) }}
                            {{ __('competitions.list.closes', ['date' => Dates::display($current->closes_at)]) }}
                        </p>
                        <a href="{{ route('competitions.show', $current->slug) }}" wire:navigate class="tone-pill mt-2 self-start px-6 py-3 text-base">
                            {{ __('competitions.list.details') }}
                            {{ \App\Support\Icon::svg('arrow-left', 'size-4', ['aria-hidden' => 'true']) }}
                        </a>
                    </div>
                    @if ($prizeTotal > 0)
                        <div class="prize-glass lg:w-auto!" data-prize-total>
                            <span class="flex items-center justify-between gap-3">
                                <span class="text-lg font-extrabold">{{ __('ui.prize.hero_title') }}</span>
                                {{ \App\Support\Icon::svg('trophy', 'size-10', ['aria-hidden' => 'true']) }}
                            </span>
                            <x-stat-badge :value="$prizeTotal" :label="__('ui.prize.currency')" class="items-start! text-start!" />
                        </div>
                    @endif
                </article>

                @if ($open->count() > 1)
                    <ul class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3" data-reveal-group>
                        @foreach ($open->skip(1) as $competition)
                            <li>@include('competitions.partials.card', ['competition' => $competition, 'status' => CompetitionStatus::Open])</li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endif

        @if ($upcoming->isNotEmpty())
            <section class="band-sky mt-4 py-8" aria-labelledby="competitions-upcoming" data-competitions-upcoming>
                <div class="mx-auto max-w-[82.5rem] px-4 sm:px-7">
                    <x-section-heading id="competitions-upcoming" icon="clock" class="mb-5">{{ __('ui.inner.competitions.upcoming') }}</x-section-heading>
                    <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3" data-reveal-group>
                        @foreach ($upcoming as $competition)
                            <li>@include('competitions.partials.card', ['competition' => $competition, 'status' => CompetitionStatus::Upcoming])</li>
                        @endforeach
                    </ul>
                </div>
            </section>
        @endif

        @if ($past->isNotEmpty())
            <section class="mx-auto max-w-[82.5rem] px-4 pt-8 pb-6 sm:px-7" aria-labelledby="competitions-past" data-competitions-past>
                <x-section-heading id="competitions-past" icon="archive-box" class="mb-5">{{ __('ui.inner.competitions.past') }}</x-section-heading>
                <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3" data-reveal-group>
                    @foreach ($past as $competition)
                        <li>@include('competitions.partials.card', ['competition' => $competition, 'status' => CompetitionStatus::Closed])</li>
                    @endforeach
                </ul>
            </section>
        @endif

        <div class="container-page pb-10">
            {{ $competitions->links('partials.pagination') }}
        </div>
    @endif
</x-layouts.app>
