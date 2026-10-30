{{--
    تحت البطل (التصور النهائي — Final-Home، وإضافة صفوان): ثلاث بطاقات بارتفاع واحد. الأولى شرائح الرؤية ثم الرسالة، والثانية
    «القيم» بشرائح لكل قيمة بأيقونتها، كلتاهما بتبديل تلقائي لطيف (x-carousel compact) يتوقف بالمرور والتركيز وزر الإيقاف،
    ولا يعمل لمن طلب تقليل الحركة. والثالثة «مجموع جوائز البراهين» ثابتة بلمعة تمر وكأس تطفو. الشرائح من اللوحة
    (HomeShowcase::identity) والفارغة لا شريحة لها، والجوائز من جدول المسابقة.

    $identity: list<array{kind, title, text, icon}>، و$prize: array{title, total, url}|null.
--}}
@php
    $statements = array_values(array_filter($identity, fn (array $slide): bool => $slide['kind'] !== 'value'));
    $values = array_values(array_filter($identity, fn (array $slide): bool => $slide['kind'] === 'value'));
    $cards = count(array_filter([$statements !== [], $values !== [], $prize !== null]));
@endphp

@if ($cards > 0)
    <section id="home-identity" class="mx-auto max-w-[82.5rem] px-4 pt-7 pb-10 sm:px-7" aria-label="{{ __('ui.identity.label') }}">
        <div @class(['grid gap-5', 'lg:grid-cols-[1.55fr_1fr_1fr]' => $cards === 3, 'lg:grid-cols-2' => $cards === 2])>
            @if ($statements !== [])
                <x-carousel :label="__('ui.identity.statements')" :count="count($statements)" autoplay :interval="6000" compact
                    class="concept-card group min-h-[10.5rem]" data-identity-slider>
                    @foreach ($statements as $index => $slide)
                        <article data-carousel-item @if ($index === 0) data-active @endif data-kind="{{ $slide['kind'] }}" class="flex flex-col gap-2.5 px-7 pt-6 pb-10">
                            <h2 class="flex items-center gap-3 text-[1.625rem] font-black text-primary-dark">
                                <x-icon-tile :icon="$slide['icon']" :index="$index" />
                                {{ $slide['title'] }}
                            </h2>
                            <p class="text-[17px] leading-[1.8] text-[var(--concept-text)]">{{ $slide['text'] }}</p>
                        </article>
                    @endforeach
                </x-carousel>
            @endif

            @if ($values !== [])
                <div class="concept-card group min-h-[10.5rem] px-6 pt-6">
                    <h2 class="flex items-center gap-3 text-[1.625rem] font-black text-primary-dark">
                        <x-icon-tile icon="heart" :index="2" />
                        {{ __('ui.identity.values') }}
                    </h2>
                    <x-carousel :label="__('ui.identity.values')" :count="count($values)" autoplay :interval="4000" compact class="static" data-values-slider>
                        @foreach ($values as $index => $slide)
                            <p data-carousel-item @if ($index === 0) data-active @endif data-kind="value"
                                class="flex items-center gap-2 pt-4 pb-10 text-[1.375rem] font-extrabold text-primary" title="{{ $slide['text'] }}">
                                {{ \App\Support\Icon::svg($slide['icon'], 'size-6 shrink-0', ['aria-hidden' => 'true']) }}
                                {{ $slide['title'] }}
                            </p>
                        @endforeach
                    </x-carousel>
                </div>
            @endif

            @if ($prize !== null)
                <a href="{{ $prize['url'] }}" @navigate($prize['url']) data-prize-card
                    class="concept-card concept-shine group flex min-h-[10.5rem] items-center justify-between px-6 py-5" data-loop>
                    <span class="relative flex flex-col gap-0.5">
                        <span class="text-[19px] font-extrabold text-primary-dark">{{ __('ui.prize.title') }}</span>
                        <span class="text-[3.375rem] leading-[1.05] font-black text-primary-dark tabular-nums" data-prize-total
                            data-count-to="{{ $prize['total'] }}" data-count-on-load>{{ number_format($prize['total']) }}</span>
                        <span class="text-base text-muted">{{ __('ui.prize.currency') }}</span>
                    </span>
                    <span class="float-icon relative text-brand" data-loop>{{ \App\Support\Icon::svg('trophy', 'size-[3.625rem]', ['aria-hidden' => 'true']) }}</span>
                </a>
            @endif
        </div>
    </section>
@endif
