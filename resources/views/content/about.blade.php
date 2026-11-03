{{--
    «عن الجمعية» (التصور النهائي — Final-About، المرحلة ج): البطل بصورة المبررات من «محتوى الرئيسية» (صورة تخص الصفحة — البندان P1
    وN5)، ثم مقاطع الصفحة من اللوحة (النصوص القصيرة بطاقات ملونة متجاورة، والبطاقات كالقيم شريطاً متحركاً)، ثم كلمة خادم الحرمين
    الشريفين، ثم كلمة رئيس مجلس الإدارة بإطار صورته (البند P4)، ثم المبررات (المقاطع الثلاثة خرجت من الرئيسية بالنسخة 63)، ثم مجلس
    الإدارة شريطاً متحركاً (البند R16)، ثم المبادرات بطاقات ملونة. يُخزَّن نصاً (PageController::about)، فلا يدخله ما يتغير بالطلب.
--}}
@php
    $content = \App\Support\HomeContent::resolved();
    $initiatives = \App\Models\Initiative::query()->published()->limit(5)->get();
    // أيقونات المبادرات بموضعها (زخرفة لا محتوى)، كما في التصور
    $initiativeIcons = ['heart', 'arrow-trending-up', 'cube', 'light-bulb', 'arrow-up'];
@endphp

<x-page-hero :title="$page->title" :summary="$page->summary" :image="$content['rationale']['image']" />

<x-content-blocks :blocks="$page->blocks" stack />

@include('home.partials.king', ['king' => $content['king']])
@include('home.partials.chairman', ['chairman' => $content['chairman']])
@include('home.partials.rationale', ['rationale' => $content['rationale']])

@if ($members->isNotEmpty())
    <section class="mx-auto max-w-[82.5rem] px-4 pt-9 pb-4 sm:px-7" aria-labelledby="board">
        <x-section-heading id="board" icon="user-group" center class="mb-5">{{ __('content.about.board') }}</x-section-heading>

        <x-marquee id="about-board-strip" :label="__('content.about.board')" :duration="max(30, $members->count() * 8)" class="media-marquee-reverse">
            @foreach ($members as $member)
                <article class="tone-card strip-card" data-person-card>
                    @if ($member->photo)
                        <img src="{{ Storage::disk('public')->url($member->photo) }}" alt="" loading="lazy" width="64" height="64" class="size-16 shrink-0 rounded-full object-cover ring-2 ring-white/40">
                    @else
                        <x-icon-tile icon="user" size="md" />
                    @endif
                    <span class="flex min-w-0 flex-col gap-0.5">
                        <span class="text-lg leading-snug font-black">{{ $member->name }}</span>
                        <span class="tone-card-muted text-sm">{{ $member->position }}</span>
                    </span>
                </article>
            @endforeach
        </x-marquee>
    </section>
@elseif (! app()->isProduction())
    {{-- مجلس الإدارة لا يظهر في الموقع حتى تُدخل أسماؤه بموافقة أصحابها --}}
    <div class="container-page py-8">
        <x-missing :what="__('content.about.board_missing')" />
    </div>
@endif

@if ($initiatives->isNotEmpty())
    <section class="mx-auto max-w-[82.5rem] px-4 pt-8 pb-12 sm:px-7" aria-labelledby="about-initiatives">
        <div class="mb-4.5 flex items-center justify-between gap-4">
            <x-section-heading id="about-initiatives" icon="flag">{{ __('content.initiatives.title') }}</x-section-heading>
            <a href="{{ route('initiatives.index') }}" wire:navigate class="more-link shrink-0">{{ __('site.view_all') }}<span class="sr-only">: {{ __('content.initiatives.title') }}</span></a>
        </div>
        <ul class="grid gap-3.5 sm:grid-cols-3 lg:grid-cols-5" data-reveal-group>
            @foreach ($initiatives as $initiative)
                <li class="tone-card">
                    <a href="{{ route('initiatives.show', $initiative->slug) }}" wire:navigate class="group flex h-full flex-col items-center gap-2.5 px-4 py-5.5 text-center">
                        <x-icon-tile :icon="$initiativeIcons[$loop->index % count($initiativeIcons)]" size="lg" />
                        <span class="text-[1.375rem] font-black">{{ $initiative->name }}</span>
                        @if (filled($initiative->summary))
                            <span class="tone-card-muted line-clamp-3 text-sm">{{ $initiative->summary }}</span>
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>
    </section>
@endif
