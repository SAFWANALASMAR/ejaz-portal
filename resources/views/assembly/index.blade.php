{{--
    قسم الجمعية العمومية (الصورة 05 — النمط الموحد): للزائر رأس القسم ثم البلاطات الأربع، ولصاحب حساب غير عضو تنبيه ورابط
    طلب الانضمام. ولمن يصل إلى منطقة الأعضاء بوابة مجلس الإدارة والجمعية العمومية (Final-Board — البند B1): ترحيب بزر «انضم
    للاجتماع القادم عبر تيمز» متى وُجد رابطه، ثم إحصائيات المجلس، ثم الاختصارات، ثم الاجتماعات القادمة، ثم المحاضر والقرارات.
    والبيانات كلها قائمة (App\Actions\Assembly\BuildBoardPortal)، ولا تُعرض «إنجازاتنا» حتى يُبنى فرعها (feature/achievements).
--}}
@php
    use App\Enums\MeetingBody;
    use App\Support\Dates;

    $tiles = $user === null ? [['route' => 'login', 'label' => 'login', 'icon' => 'user']] : [];

    if ($user === null || $member) {
        $tiles = [
            ...$tiles,
            ['route' => 'assembly.meetings.index', 'label' => 'meetings', 'icon' => 'calendar-days'],
            ['route' => 'assembly.minutes.index', 'label' => 'minutes', 'icon' => 'document-text'],
            ['route' => 'assembly.files.index', 'label' => 'files', 'icon' => 'folder'],
        ];
    }

    $next = $portal['upcoming'][0] ?? null;
    $joinable = fn ($meeting): bool => $meeting->is_online && filled($meeting->online_join_url);
@endphp

<x-layouts.app :title="__('assembly.title')">
    @if ($portal === null)
        <x-page-hero :title="__('assembly.title')" />
        <x-page-sections />

        <section class="py-10 sm:py-14">
            <div class="container-page">
                @if ($tiles !== [])
                    <h2 class="sr-only">{{ __('assembly.index.tiles') }}</h2>
                    <ul class="grid grid-cols-2 gap-4 {{ count($tiles) === 4 ? 'lg:grid-cols-4' : 'sm:grid-cols-3' }}">
                        @foreach ($tiles as $tile)
                            <li><x-section-tile :index="$loop->index" :href="route($tile['route'])" :icon="$tile['icon']" :label="__('assembly.index.'.$tile['label'])" /></li>
                        @endforeach
                    </ul>
                @else
                    <x-alert type="info">
                        <p>{{ __('assembly.index.not_member') }}</p>
                        @if (Route::has('services.assembly-membership'))
                            <x-button class="mt-4" :href="route('services.assembly-membership')" arrow>{{ __('assembly.index.join') }}</x-button>
                        @endif
                    </x-alert>
                @endif
            </div>
        </section>
    @else
        <div class="mx-auto flex max-w-[82.5rem] flex-col gap-6 px-4 pt-7 pb-14 sm:px-7" data-board-portal>
            {{-- الترحيب: صورة البطل بتدرجه، والاجتماع القادم وزر تيمز متى وُجد رابطه --}}
            <section class="concept-hero rounded-[1.25rem]" data-size="page" aria-labelledby="board-welcome">
                <div class="concept-hero-media">
                    <img src="{{ asset('images/placeholders/architecture.svg') }}" alt="" aria-hidden="true" width="1280" height="208">
                </div>
                <div class="concept-hero-scrim" aria-hidden="true"></div>
                <div class="relative flex min-h-[12.5rem] flex-col justify-center gap-4 px-6 py-8 sm:flex-row sm:items-center sm:justify-between sm:px-8">
                    <div class="flex items-center gap-5">
                        <span class="inline-flex size-[4.25rem] shrink-0 items-center justify-center rounded-full border border-white/25 bg-white/10" aria-hidden="true">
                            {{ \App\Support\Icon::svg('user-group', 'size-8') }}
                        </span>
                        <div>
                            <p class="rise-1 text-sm font-extrabold text-white/85">{{ __('assembly.portal.eyebrow') }}</p>
                            <h1 id="board-welcome" class="rise-1 text-2xl font-black sm:text-[2.25rem]">{{ __('assembly.portal.welcome', ['name' => $user->name]) }}</h1>
                            <p class="rise-2 mt-1 text-base text-white/90 sm:text-lg">
                                @if ($next)
                                    {{ __('assembly.portal.next', ['date' => Dates::display($next->starts_at, withTime: true)]) }} —
                                @endif
                                {{ __('assembly.portal.intro') }}
                            </p>
                        </div>
                    </div>
                    @if ($next && $joinable($next))
                        <a href="{{ $next->online_join_url }}" target="_blank" rel="noopener noreferrer" data-board-join-next
                            class="inline-flex shrink-0 items-center justify-center gap-2 self-start rounded-full border border-white/40 bg-white/15 px-5 py-3 font-extrabold text-white transition hover:bg-white/25 sm:self-center">
                            {{ \App\Support\Icon::svg('video-camera', 'size-5', ['aria-hidden' => 'true']) }}
                            {{ __('assembly.portal.join_next') }}
                            <span class="sr-only">({{ __('site.opens_in_new_tab') }})</span>
                        </a>
                    @endif
                </div>
            </section>

            {{-- إحصائيات المجلس من البيانات القائمة --}}
            <section aria-labelledby="board-stats">
                <h2 id="board-stats" class="sr-only">{{ __('assembly.portal.stats') }}</h2>
                @php($shown = count(array_filter($portal['stats'], fn (?int $value): bool => $value !== null)))
                <ul @class(['grid grid-cols-2 gap-3.5', 'lg:grid-cols-4' => $shown === 4, 'lg:grid-cols-3' => $shown === 3]) data-board-stats>
                    @foreach (['members' => 'user-group', 'meetings_this_year' => 'calendar-days', 'approved_minutes' => 'document-check', 'open_assignments' => 'clipboard-document-list'] as $key => $icon)
                        @continue($portal['stats'][$key] === null)
                        <li class="tone-card flex items-center gap-3 p-4">
                            <x-icon-tile :icon="$icon" size="sm" />
                            <span class="flex min-w-0 flex-col">
                                <span class="text-2xl font-black tabular-nums">{{ number_format($portal['stats'][$key]) }}</span>
                                <span class="tone-card-muted text-sm font-bold">{{ __('assembly.portal.'.$key) }}</span>
                            </span>
                        </li>
                    @endforeach
                </ul>
            </section>

            {{-- الاختصارات --}}
            <nav aria-label="{{ __('assembly.portal.shortcuts') }}">
                <ul class="grid grid-cols-1 gap-3.5 sm:grid-cols-3" data-board-shortcuts>
                    @foreach ($tiles as $tile)
                        <li class="flex">
                            <a href="{{ route($tile['route']) }}" @navigate(route($tile['route'])) class="tone-card group flex w-full items-center gap-3.5 px-5 py-4">
                                <x-icon-tile :icon="$tile['icon']" size="md" />
                                <span class="text-xl font-black">{{ __('assembly.index.'.$tile['label']) }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>

            {{-- الاجتماعات القادمة: لكل اجتماع جهته وموعده وبنوده، وزر تيمز متى وُجد رابطه --}}
            <section class="concept-panel rounded-[1.25rem] p-5 sm:p-6" aria-labelledby="board-upcoming">
                <div class="flex items-center justify-between gap-3">
                    <h2 id="board-upcoming" class="flex items-center gap-2.5 text-[1.375rem] font-black text-primary-dark sm:text-2xl">
                        <x-icon-tile icon="calendar-days" size="sm" shade="dark" />
                        {{ __('assembly.portal.upcoming') }}
                    </h2>
                    <a href="{{ route('assembly.meetings.index') }}" wire:navigate class="more-link shrink-0">{{ __('assembly.portal.all_meetings') }}</a>
                </div>

                @if ($portal['upcoming']->isEmpty())
                    <p class="mt-4 text-muted">{{ __('assembly.portal.upcoming_empty') }}</p>
                @else
                    <ul class="mt-4 grid gap-3.5 md:grid-cols-2 xl:grid-cols-3" data-board-meetings>
                        @foreach ($portal['upcoming'] as $meeting)
                            @php($starts = Dates::local($meeting->starts_at))
                            <li class="tone-card flex flex-col gap-3 p-4.5">
                                <div class="flex items-start gap-3.5">
                                    <span class="flex size-15 flex-none flex-col items-center justify-center rounded-[0.875rem] border border-current/30 bg-white/15 leading-tight font-black">
                                        <span class="text-xl tabular-nums">{{ $starts->translatedFormat('j') }}</span>
                                        <span class="text-xs">{{ $starts->translatedFormat('F') }}</span>
                                    </span>
                                    <span class="flex min-w-0 flex-col gap-1">
                                        <span class="tone-card-muted text-sm font-extrabold">{{ $meeting->body === MeetingBody::Committee && $meeting->committee ? $meeting->committee->name : $meeting->body->getLabel() }}</span>
                                        <span class="text-lg font-black">{{ $meeting->title }}</span>
                                        <span class="tone-card-muted text-sm">
                                            {{ Dates::display($meeting->starts_at, withTime: true) }}
                                            — {{ $meeting->is_online ? __('assembly.portal.remote') : ($meeting->location ?? '') }}
                                            @if ($meeting->agenda_items_count > 0)
                                                — {{ trans_choice('assembly.portal.items', $meeting->agenda_items_count) }}
                                            @endif
                                        </span>
                                    </span>
                                </div>
                                <div class="mt-auto flex flex-wrap gap-2">
                                    @if ($joinable($meeting))
                                        <a href="{{ $meeting->online_join_url }}" target="_blank" rel="noopener noreferrer" data-board-join class="tone-pill">
                                            {{ \App\Support\Icon::svg('video-camera', 'size-4', ['aria-hidden' => 'true']) }}
                                            {{ __('assembly.portal.join') }}
                                            <span class="sr-only">({{ __('site.opens_in_new_tab') }})</span>
                                        </a>
                                    @endif
                                    @can('attend', $meeting)
                                        <a href="{{ route('assembly.meetings.show', $meeting) }}" wire:navigate class="tone-pill">{{ __('assembly.portal.details') }}</a>
                                    @endcan
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            {{-- المحاضر والقرارات: المحاضر المعتمدة للجمعية العمومية، والقرارات لمن يرى بنود الاجتماعات في اللوحة --}}
            <section class="concept-panel rounded-[1.25rem] p-5 sm:p-6" aria-labelledby="board-records">
                <div class="flex items-center justify-between gap-3">
                    <h2 id="board-records" class="flex items-center gap-2.5 text-[1.375rem] font-black text-primary-dark sm:text-2xl">
                        <x-icon-tile icon="document-text" size="sm" shade="medium" />
                        {{ __('assembly.portal.records') }}
                    </h2>
                    <a href="{{ route('assembly.minutes.index') }}" wire:navigate class="more-link shrink-0">{{ __('assembly.portal.records_all') }}</a>
                </div>

                @if ($portal['minutes']->isEmpty() && ($portal['decisions'] ?? collect())->isEmpty())
                    <p class="mt-4 text-muted">{{ __('assembly.portal.records_empty') }}</p>
                @else
                    <ul class="mt-4 grid gap-3 md:grid-cols-2" data-board-records>
                        @foreach ($portal['decisions'] ?? [] as $item)
                            <li class="tone-card flex items-center gap-3.5 p-4">
                                <x-icon-tile icon="check-badge" size="sm" />
                                <span class="flex min-w-0 flex-1 flex-col gap-0.5">
                                    <span class="line-clamp-2 font-extrabold">{{ $item->decision }}</span>
                                    <span class="tone-card-muted text-sm">{{ $item->meeting->title }} — {{ Dates::display($item->meeting->starts_at) }}</span>
                                </span>
                                <span class="keep-color">@include('account.requests.status', ['status' => $item->status])</span>
                            </li>
                        @endforeach
                        @foreach ($portal['minutes'] as $meeting)
                            <li class="tone-card flex items-center gap-3.5 p-4">
                                <x-icon-tile icon="document-text" size="sm" />
                                <span class="flex min-w-0 flex-1 flex-col gap-0.5">
                                    <a href="{{ $meeting->minutesUrl() }}" class="line-clamp-2 font-extrabold hover:underline">{{ __('assembly.portal.minutes_of', ['title' => $meeting->title]) }}</a>
                                    <span class="tone-card-muted text-sm">{{ __('assembly.minutes.approved_on', ['date' => Dates::display($meeting->minutes_approved_at)]) }}</span>
                                </span>
                                <span class="tone-chip">{{ __('assembly.portal.minutes_approved') }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>
    @endif
</x-layouts.app>
