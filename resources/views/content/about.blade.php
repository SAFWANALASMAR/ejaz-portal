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
    // «من نحن» يميناً في بطاقة ناعمة، والرؤية والرسالة يساراً اقتباسين (إضافة توجيه المدير، 3 نوفمبر 2026، البند ٢٠): مقاطع نص من
    // محرر الصفحة بعناوينها، تخرج من بقية المقاطع، ويبقى غيرها كما كان
    $blocks = \App\Support\ContentBlocks::visible($page->blocks);
    $headingOf = fn (array $block): string => $block['type'] === 'text' && is_string($block['data']['heading'] ?? null) ? trim($block['data']['heading']) : '';
    $who = collect($blocks)->first(fn (array $block): bool => $headingOf($block) === __('content.about.who_heading'));
    // «التوجه الاستراتيجي» أول الصفحة (البند ٢٨): نصه من مقطع بعنوانه، وإلا علامة «【ناقص】»
    $strategy = collect($blocks)->first(fn (array $block): bool => $headingOf($block) === __('content.about.strategy_heading'));
    $quotes = collect([__('ui.identity.vision'), __('ui.identity.mission')])
        ->mapWithKeys(fn (string $heading): array => [$heading => collect($blocks)->first(fn (array $block): bool => $headingOf($block) === $heading)])
        ->filter();
    $rest = array_values(array_filter($blocks, fn (array $block): bool => $block !== $who && $block !== $strategy && ! $quotes->contains($block)));
@endphp

<x-page-hero :title="$page->title" :summary="$page->summary" :image="$content['rationale']['image']" />

{{-- مراسي القائمة المنسدلة «عن الجمعية» (البند ٢٨): about-strategy ثم about-who ثم about-vision وabout-mission ثم about-values، بإزاحة
     الرأس الثابت (scroll-mt-48)، ومع التنقّل السريع (app.js) --}}
<section id="about-strategy" class="mx-auto max-w-[82.5rem] scroll-mt-48 px-4 pt-7 sm:px-7" aria-labelledby="about-strategy-title" data-about-strategy>
    <div class="tone-card about-who flex flex-col gap-3" data-tone="dark">
        <h2 id="about-strategy-title" class="text-[1.75rem] font-black">{{ __('content.about.strategy_heading') }}</h2>
        @if ($strategy !== null)
            <div class="prose-ar text-[17px] leading-[1.9]">{{ \App\Support\RichText::toHtml(is_string($strategy['data']['body'] ?? null) ? $strategy['data']['body'] : null) }}</div>
        @else
            <x-missing :what="__('content.about.strategy_missing')" class="self-start" />
        @endif
    </div>
</section>

@if ($who !== null || $quotes->isNotEmpty())
    <section class="mx-auto max-w-[82.5rem] px-4 pt-7 pb-8 sm:px-7" aria-label="{{ __('content.about.intro_label') }}" data-about-intro>
        <div @class(['grid gap-8', 'lg:grid-cols-2 lg:items-center' => $who !== null && $quotes->isNotEmpty()])>
            @if ($who !== null)
                <article id="about-who" class="tone-card about-who flex scroll-mt-48 flex-col gap-3" data-tone="soft" data-about-who>
                    <h2 class="text-[1.75rem] font-black">{{ $who['data']['heading'] }}</h2>
                    <div class="prose-ar text-[17px] leading-[1.9]">{{ \App\Support\RichText::toHtml(is_string($who['data']['body'] ?? null) ? $who['data']['body'] : null) }}</div>
                </article>
            @endif

            @if ($quotes->isNotEmpty())
                <div class="flex flex-col gap-9" data-about-quotes>
                    @foreach ($quotes as $heading => $block)
                        <blockquote class="about-quote scroll-mt-48" data-about-quote="{{ $heading === __('ui.identity.vision') ? 'vision' : 'mission' }}"
                            @if ($anchor = \App\Support\ContentBlocks::anchor($heading)) id="{{ $anchor }}" @endif>
                            <span class="about-quote-mark" aria-hidden="true">”</span>
                            <p class="about-quote-label">{{ $heading }}</p>
                            <div class="about-quote-text">{{ \App\Support\RichText::toHtml(is_string($block['data']['body'] ?? null) ? $block['data']['body'] : null) }}</div>
                        </blockquote>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
@endif

<x-content-blocks :blocks="$rest" stack />

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
@elseif (\App\Support\MissingText::visible())
    {{-- مجلس الإدارة لا يظهر في الموقع حتى تُدخل أسماؤه بموافقة أصحابها --}}
    <div class="container-page py-8">
        <x-missing :what="__('content.about.board_missing')" />
    </div>
@endif

@if ($initiatives->isNotEmpty())
    <section class="mx-auto max-w-[82.5rem] px-4 pt-8 pb-12 sm:px-7" aria-labelledby="about-initiatives">
        <div class="mb-4.5 flex items-center justify-between gap-4">
            <x-section-heading id="about-initiatives" icon="flag" class="scroll-mt-48">{{ __('content.initiatives.title') }}</x-section-heading>
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
