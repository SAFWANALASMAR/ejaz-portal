{{--
    صفحة المسابقة: رأس القسم بعنوانها، ثم الوصف والشروط والجوائز، وبطاقة جانبية بالحالة والموعدين وزر
    التقديم حين تكون مفتوحة (أو متابعة المشاركة لمن بدأها).
--}}
@php
    use App\Enums\CompetitionStatus;
    use App\Support\CompetitionPage;
    use App\Support\Dates;
    use App\Support\HomeContent;
    use App\Support\HomeShowcase;
    use App\Support\Icon;
    use App\Support\StructuredData;

    $status = $competition->status();
    // مسابقة الطلاب (Final-Competition): شارة الموسم وسطر الجهة المنسَّق معها وبطاقة الجوائز في البطل، كلها من اللوحة
    $season = $competition->student_mode ? $competition->pageText(CompetitionPage::SEASON) : null;
    $partner = $competition->student_mode ? $competition->pageText(CompetitionPage::PARTNER) : null;
    $prizeTotal = $competition->student_mode ? HomeShowcase::prizeTotal($competition) : 0;
    // صورة المسابقة (البند P3): صورة شريحتها في بطل الرئيسية من «محتوى الرئيسية»، فلا موضع صورة جديد، وإلا الرسم المؤقت
    $heroImage = $competition->student_mode ? HomeContent::heroSlideUrl('brahin') : null;
@endphp

<x-layouts.app :title="$competition->title" :description="StructuredData::text($competition->description, 160)">
    {{-- المسابقة فعاليةً ومسار التنقل إليها (StructuredData). وبلا الفعالية ما دامت مواعيدها لم تُعلن: الفعالية بلا تاريخ بدء خطأ عند Google --}}
    @push('head')
        {{ StructuredData::script([
            ...($competition->schedule_pending ? [] : [StructuredData::competition($competition)]),
            StructuredData::breadcrumbs($competition->title, route('competitions.show', $competition->slug), [[__('competitions.title'), route('competitions.index')]]),
        ]) }}
    @endpush
    {{-- بطل مسابقة البراهين (Final-Competition، المرحلة ج): الموسم والجهة المنسَّق معها من نصوص الصفحة في اللوحة، وفارغين علامة
         【ناقص】 في التطوير. و«بادر بالتسجيل» تحت العنوان على الحاسوب، وعلى الجوال بعرض كامل تحت بطاقة الجوائز (العنوان ثم البطاقة
         ثم الزر)، وكلاهما من x-competitions.student-cta بحالاته --}}
    <x-page-hero :title="$competition->title" :eyebrow="$season" :image="$heroImage" :compact="$competition->student_mode">
        @if ($partner)
            <p data-hero-partner class="rise-3 text-base text-white/90">{{ __('ui.prize.coordination', ['partner' => $partner]) }}</p>
        @endif
        @if ($competition->student_mode)
            @if (blank($season))
                <x-missing :what="__('ui.inner.season_missing')" class="self-start" />
            @endif
            @if (blank($partner))
                <x-missing :what="__('ui.inner.partner_missing')" class="self-start" />
            @endif
            <x-competitions.student-cta :competition="$competition" :submission="$submission" :status="$status" register data-at="desktop" class="rise-3 mt-1" />
        @endif

        @if ($competition->student_mode)
            <x-slot:aside>
                <div class="prize-glass concept-shine rise-3" data-prize-total data-loop>
                    @if ($prizeTotal > 0)
                        <span class="relative flex items-center justify-between gap-3">
                            <span class="text-lg font-extrabold">{{ __('ui.prize.hero_title') }}</span>
                            <span class="float-icon text-white" data-loop>{{ Icon::svg('trophy', 'size-11', ['aria-hidden' => 'true']) }}</span>
                        </span>
                        <x-stat-badge :value="$prizeTotal" :label="__('ui.prize.currency')" on-load class="relative items-start! text-start!" />
                    @endif
                    <x-competitions.student-cta :competition="$competition" :submission="$submission" :status="$status" hero class="relative mt-2" />
                </div>
                <x-competitions.student-cta :competition="$competition" :submission="$submission" :status="$status" register data-at="mobile" />
            </x-slot:aside>
        @endif
    </x-page-hero>

    <section @class(['py-10 sm:py-14' => ! $competition->student_mode, 'py-7 sm:py-9' => $competition->student_mode])>
        <div class="container-page grid gap-8 lg:grid-cols-[1fr_22rem]">
            <div class="min-w-0 space-y-8">
                <x-flash />

                @foreach (['description', 'terms', 'prizes'] as $field)
                    @if (filled($competition->{$field}))
                        <div>
                            <x-section-heading>{{ __('competitions.show.'.$field) }}</x-section-heading>
                            <p class="measure mt-4 leading-8 whitespace-pre-line text-ink">{{ $competition->{$field} }}</p>
                        </div>
                    @endif
                @endforeach

                @if ($competition->student_mode)
                    @include('competitions.student._page')
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

            <aside class="competition-aside h-fit space-y-4 card-lift p-6">
                <x-competitions.status-badge :status="$status" />
                {{-- «المواعيد تُعلن لاحقاً»: بلا الموعدين المخزنين، ومكانهما سطر الزر أدناه (schedule_pending) --}}
                @unless ($competition->schedule_pending)
                <h2 class="font-bold text-primary">{{ __('competitions.show.dates') }}</h2>
                <dl class="space-y-2 text-sm" data-competition-dates>
                    <div class="flex items-center gap-2">
                        {{ \App\Support\Icon::svg('calendar', 'size-4 shrink-0 text-accent', ['aria-hidden' => 'true']) }}
                        <dt class="text-muted">{{ $competition->student_mode ? __('competitions.student.registration_opens') : __('competitions.show.opens_at') }}</dt>
                        <dd class="font-medium text-ink">{{ Dates::display($competition->opens_at, withTime: true) }}</dd>
                    </div>
                    <div class="flex items-center gap-2">
                        {{ \App\Support\Icon::svg('calendar', 'size-4 shrink-0 text-accent', ['aria-hidden' => 'true']) }}
                        <dt class="text-muted">{{ $competition->student_mode ? __('competitions.student.registration_closes') : __('competitions.show.closes_at') }}</dt>
                        <dd class="font-medium text-ink">{{ Dates::display($competition->closes_at, withTime: true) }}</dd>
                    </div>
                </dl>
                @endunless

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
                    <p class="text-sm text-muted" @if ($competition->schedule_pending) data-schedule-pending @endif>
                        {{ $competition->schedule_pending ? __('competitions.show.schedule_pending') : __('competitions.show.upcoming', ['date' => Dates::display($competition->opens_at, withTime: true)]) }}
                    </p>
                @else
                    <p class="text-sm text-muted">{{ __('competitions.show.closed') }}</p>
                @endif
            </aside>
        </div>
    </section>
</x-layouts.app>
