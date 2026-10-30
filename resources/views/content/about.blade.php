{{--
    «عن الجمعية» (التصور النهائي — Final-About): رأس القسم، ثم مقاطع الصفحة من اللوحة (النصوص القصيرة المتتالية بطاقات متجاورة
    بأيقوناتها، والبطاقات مرقمة على الشريط السماوي)، ثم كلمة رئيس مجلس الإدارة في بطاقة (نصها المعتمد من «محتوى الرئيسية»)،
    ثم مجلس الإدارة، ثم المبادرات. يُخزَّن نصاً (PageController::about)، فلا يدخله ما يتغير بالطلب.
--}}
@php
    $chairman = \App\Support\HomeContent::resolved()['chairman'];
    $initiatives = \App\Models\Initiative::query()->published()->limit(5)->get();
    // أيقونات المبادرات بموضعها (زخرفة لا محتوى)، كما في التصور
    $initiativeIcons = ['heart', 'arrow-trending-up', 'cube', 'light-bulb', 'arrow-up'];
@endphp

<x-page-hero :title="$page->title" :summary="$page->summary" />

<x-content-blocks :blocks="$page->blocks" stack />

{{-- كلمة رئيس مجلس الإدارة: الصورة، والمقتطف، والاسم والصفة، والكلمة كاملة تنفتح في مكانها --}}
<section class="mx-auto max-w-[82.5rem] px-4 pt-12 pb-6 sm:px-7" aria-labelledby="about-chairman">
    <div class="card-lift flex flex-col gap-8 p-7 md:flex-row md:items-center" data-reveal>
        <img src="{{ $chairman['image'] }}" alt="{{ $chairman['image_alt'] }}" loading="lazy" width="220" height="240"
            class="h-60 w-full shrink-0 rounded-[1.25rem] bg-[var(--concept-sky)] object-cover object-top shadow-[0_20px_40px_-24px_rgba(31,69,101,.5)] md:w-[13.75rem]">
        <div class="flex flex-col gap-3">
            <h2 id="about-chairman" class="self-start rounded-full bg-primary-soft px-3.5 py-1 text-sm font-extrabold text-primary">{{ $chairman['heading'] }}</h2>
            <div class="space-y-2 text-xl leading-[1.9] text-[var(--concept-text)]">
                @foreach (\App\Support\HomeContent::paragraphs($chairman['excerpt']) as $paragraph)
                    <p>{{ $paragraph }}</p>
                @endforeach
            </div>
            <p class="flex flex-col border-s-[3px] border-brand ps-3">
                <span class="text-[19px] font-black text-primary-dark">{{ $chairman['name'] }}</span>
                <span class="text-[15px] text-muted">{{ $chairman['title'] }}</span>
            </p>
            <details class="group">
                <summary class="inline-flex cursor-pointer list-none items-center gap-2 rounded-full bg-primary-dark px-5.5 py-2.5 font-extrabold text-white transition hover:bg-brand [&::-webkit-details-marker]:hidden">
                    {{ __('home.sections.chairman.read_more') }}
                    {{ \App\Support\Icon::svg('arrow-left', 'size-4', ['aria-hidden' => 'true']) }}
                </summary>
                <div class="mt-5 space-y-4 leading-8">
                    @foreach (\App\Support\HomeContent::paragraphs($chairman['full']) as $paragraph)
                        <p>{{ $paragraph }}</p>
                    @endforeach
                </div>
            </details>
        </div>
    </div>
</section>

@if ($members->isNotEmpty())
    <section class="mx-auto max-w-[82.5rem] px-4 pt-8 pb-6 sm:px-7" aria-labelledby="board">
        <x-section-heading id="board" center class="mb-6">{{ __('content.about.board') }}</x-section-heading>

        <ul class="grid gap-4.5 sm:grid-cols-2 lg:grid-cols-3" data-reveal-group>
            @foreach ($members as $member)
                <li class="card-lift group flex items-center gap-4 p-4.5" data-person-card>
                    @if ($member->photo)
                        <img src="{{ Storage::disk('public')->url($member->photo) }}" alt="" loading="lazy" width="76" height="76" class="size-19 shrink-0 rounded-full object-cover">
                    @else
                        <span class="flex size-19 shrink-0 items-center justify-center rounded-full bg-linear-to-br from-[var(--concept-sky)] to-[var(--concept-pale)] text-primary" aria-hidden="true">
                            {{ \App\Support\Icon::svg('user', 'size-8') }}
                        </span>
                    @endif
                    <span class="flex flex-col gap-1">
                        <span class="text-[19px] font-black text-primary-dark">{{ $member->name }}</span>
                        <span class="text-[15px] text-muted">{{ $member->position }}</span>
                    </span>
                </li>
            @endforeach
        </ul>
    </section>
@elseif (! app()->isProduction())
    {{-- مجلس الإدارة لا يظهر في الموقع حتى تُدخل أسماؤه بموافقة أصحابها --}}
    <div class="container-page py-8">
        <x-missing :what="__('content.about.board_missing')" />
    </div>
@endif

@if ($initiatives->isNotEmpty())
    <section class="mx-auto max-w-[82.5rem] px-4 pt-9 pb-14 sm:px-7" aria-labelledby="about-initiatives">
        <div class="mb-4.5 flex items-center justify-between gap-4">
            <h2 id="about-initiatives" class="text-[1.875rem] font-black text-primary-dark">{{ __('content.initiatives.title') }}</h2>
            <a href="{{ route('initiatives.index') }}" wire:navigate class="more-link">{{ __('site.view_all') }}</a>
        </div>
        <ul class="grid gap-3.5 sm:grid-cols-3 lg:grid-cols-5" data-reveal-group>
            @foreach ($initiatives as $initiative)
                <li>
                    <a href="{{ route('initiatives.show', $initiative->slug) }}" wire:navigate class="card-lift group flex h-full flex-col items-center gap-2.5 px-4 py-5.5 text-center">
                        <x-icon-tile :icon="$initiativeIcons[$loop->index % count($initiativeIcons)]" :index="$loop->index" size="lg" />
                        <span class="text-[1.375rem] font-black text-primary-dark">{{ $initiative->name }}</span>
                        @if (filled($initiative->summary))
                            <span class="line-clamp-3 text-sm text-muted">{{ $initiative->summary }}</span>
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>
    </section>
@endif
