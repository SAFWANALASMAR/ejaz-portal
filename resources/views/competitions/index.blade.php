{{--
    قائمة المسابقات (نمط «برامجنا» — الصورة 06): رأس القسم، ثم شبكة بطاقات بصورة عليها شارة الحالة،
    ثم العنوان والوصف، ثم الموعد بأيقونة تقويم، ثم زر رئيسي بعرض كامل.
--}}
@php
    use App\Enums\CompetitionStatus;
    use App\Support\Dates;
@endphp

<x-layouts.app :title="__('competitions.title')">
    <x-page-hero :title="__('competitions.list.heading')" />

    <section class="py-10 sm:py-14">
        <div class="container-page">
            @if ($competitions->isEmpty())
                <p class="text-muted">{{ __('competitions.list.empty') }}</p>
            @else
                <ul class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($competitions as $competition)
                        @php($status = $competition->status())
                        <li class="flex h-full flex-col overflow-hidden rounded-2xl border border-line/70 bg-white shadow-xs">
                            <div class="relative">
                                <img src="{{ asset('images/placeholders/architecture.svg') }}" alt="" class="aspect-[16/9] w-full object-cover">
                                <x-competitions.status-badge :status="$status" class="absolute top-3 start-3" />
                            </div>
                            <div class="flex flex-1 flex-col p-5">
                                <h2 class="text-lg font-bold text-ink">{{ $competition->title }}</h2>
                                @if (filled($competition->description))
                                    <p class="mt-2 line-clamp-3 text-sm leading-6 text-muted">{{ $competition->description }}</p>
                                @endif
                                <p class="mt-4 inline-flex items-center gap-1.5 text-sm text-muted">
                                    {{ svg('heroicon-o-calendar', 'size-4 shrink-0', ['aria-hidden' => 'true']) }}
                                    @if ($status === CompetitionStatus::Upcoming)
                                        {{ __('competitions.list.opens', ['date' => Dates::display($competition->opens_at)]) }}
                                    @elseif ($status === CompetitionStatus::Open)
                                        {{ __('competitions.list.closes', ['date' => Dates::display($competition->closes_at)]) }}
                                    @else
                                        {{ __('competitions.list.closed_on', ['date' => Dates::display($competition->closes_at)]) }}
                                    @endif
                                </p>
                                <div class="mt-auto pt-5">
                                    <x-button :href="route('competitions.show', $competition->slug)" class="w-full" arrow>{{ __('competitions.list.details') }}</x-button>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>

                {{ $competitions->links('partials.pagination') }}
            @endif
        </div>
    </section>
</x-layouts.app>
