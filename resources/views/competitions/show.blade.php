{{--
    صفحة المسابقة: رأس القسم بعنوانها، ثم الوصف والشروط والجوائز، وبطاقة جانبية بالحالة والموعدين وزر
    التقديم حين تكون مفتوحة (أو متابعة المشاركة لمن بدأها).
--}}
@php
    use App\Enums\CompetitionStatus;
    use App\Support\Dates;
    use App\Support\StructuredData;

    $status = $competition->status();
@endphp

<x-layouts.app :title="$competition->title" :description="$competition->description">
    {{-- المسابقة فعاليةً ومسار التنقل إليها (StructuredData) --}}
    @push('head')
        {{ StructuredData::script([
            StructuredData::competition($competition),
            StructuredData::breadcrumbs($competition->title, route('competitions.show', $competition->slug), [[__('competitions.title'), route('competitions.index')]]),
        ]) }}
    @endpush
    <x-page-hero :title="$competition->title" />

    <section class="py-10 sm:py-14">
        <div class="container-page grid gap-8 lg:grid-cols-[1fr_22rem]">
            <div class="min-w-0 space-y-8">
                <x-flash />

                @foreach (['description', 'terms', 'prizes'] as $field)
                    @if (filled($competition->{$field}))
                        <div>
                            <x-section-heading>{{ __('competitions.show.'.$field) }}</x-section-heading>
                            <p class="mt-4 leading-8 whitespace-pre-line text-ink">{{ $competition->{$field} }}</p>
                        </div>
                    @endif
                @endforeach

                @if ($competition->student_mode)
                    @include('competitions.student._page')
                    @include('competitions.student._rounds')
                @endif

                @if ($competition->categoryList() !== [])
                    <div>
                        <x-section-heading>{{ __('competitions.show.categories') }}</x-section-heading>
                        <ul class="mt-4 flex flex-wrap gap-2">
                            @foreach ($competition->categoryList() as $category)
                                <li class="rounded-full border border-line bg-white px-4 py-1.5 text-sm text-ink">{{ $category }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>

            <aside class="h-fit space-y-4 rounded-2xl border border-line/70 bg-white p-6 shadow-xs lg:sticky lg:top-24">
                <x-competitions.status-badge :status="$status" />
                <h2 class="font-bold text-primary">{{ __('competitions.show.dates') }}</h2>
                <dl class="space-y-2 text-sm">
                    <div class="flex items-center gap-2">
                        {{ svg('heroicon-o-calendar', 'size-4 shrink-0 text-accent', ['aria-hidden' => 'true']) }}
                        <dt class="text-muted">{{ $competition->student_mode ? __('competitions.student.registration_opens') : __('competitions.show.opens_at') }}</dt>
                        <dd class="font-medium text-ink">{{ Dates::display($competition->opens_at, withTime: true) }}</dd>
                    </div>
                    <div class="flex items-center gap-2">
                        {{ svg('heroicon-o-calendar', 'size-4 shrink-0 text-accent', ['aria-hidden' => 'true']) }}
                        <dt class="text-muted">{{ $competition->student_mode ? __('competitions.student.registration_closes') : __('competitions.show.closes_at') }}</dt>
                        <dd class="font-medium text-ink">{{ Dates::display($competition->closes_at, withTime: true) }}</dd>
                    </div>
                </dl>

                @if ($competition->student_mode)
                    @include('competitions.student._aside')
                @elseif ($submission?->reference)
                    <x-alert type="success">{{ __('competitions.show.submitted', ['reference' => $submission->reference]) }}</x-alert>
                    <x-button :href="route('competitions.entry', $competition->slug)" variant="outline" class="w-full">{{ __('competitions.show.view_entry') }}</x-button>
                @elseif ($status === CompetitionStatus::Open)
                    @auth
                        @if ($submission)
                            <x-button :href="route('competitions.entry', $competition->slug)" class="w-full" arrow>{{ __('competitions.show.continue') }}</x-button>
                        @else
                            {{-- المسودة تُنشأ بـ POST، فلا ينشئها فتح رابط --}}
                            <form method="POST" action="{{ route('competitions.entry.start', $competition->slug) }}">
                                @csrf
                                <x-button class="w-full" arrow>{{ __('competitions.show.apply') }}</x-button>
                            </form>
                        @endif
                    @else
                        <x-button :href="route('login')" class="w-full" arrow>{{ __('competitions.show.login_to_apply') }}</x-button>
                    @endauth
                @elseif ($status === CompetitionStatus::Upcoming)
                    <p class="text-sm text-muted">{{ __('competitions.show.upcoming', ['date' => Dates::display($competition->opens_at, withTime: true)]) }}</p>
                @else
                    <p class="text-sm text-muted">{{ __('competitions.show.closed') }}</p>
                @endif
            </aside>
        </div>
    </section>
</x-layouts.app>
