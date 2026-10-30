{{--
    تحت البطل (مرجع المدير التنفيذي، وإضافة صفوان 30 أكتوبر — plans/2026-10-30-director-home-reference.md): شرائح الهوية
    المتحركة (الرؤية، ثم الرسالة، ثم القيم واحدة واحدة بأيقوناتها) في x-carousel بتبديل تلقائي لطيف، وبجانبها بطاقة «مجموع
    جوائز البراهين» ثابتة بعدّاد. الشرائح من اللوحة (HomeShowcase::identity)، والفارغة لا شريحة لها، والجوائز من جدول المسابقة.
    ترتفع فوق حافة البطل قليلاً.

    $identity: list<array{kind, title, text, icon}>، و$prize: array{title, total, url}|null.
--}}
@php($count = count($identity))

@if ($count > 0 || $prize !== null)
    <section id="home-identity" class="relative z-10 -mt-24 pb-6 sm:-mt-20" aria-label="{{ __('ui.identity.label') }}">
        <div @class(['container-page grid gap-5', 'lg:grid-cols-[1fr_22rem]' => $count > 0 && $prize !== null])>
            @if ($count > 0)
                <x-carousel :label="__('ui.identity.label')" :count="$count" autoplay :interval="6500"
                    class="card-glow p-6 sm:p-8" data-identity-slider>
                    @foreach ($identity as $index => $slide)
                        <article data-carousel-item @if ($index === 0) data-active @endif data-kind="{{ $slide['kind'] }}"
                            class="flex items-start gap-5">
                            <x-icon-tile :icon="$slide['icon']" :tone="['vision' => 'blue', 'mission' => 'violet'][$slide['kind']] ?? ['green', 'sky', 'navy', 'violet'][$index % 4]" size="lg" />
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-accent">
                                    {{ $slide['kind'] === 'value' ? __('ui.identity.values') : __('ui.identity.label') }}
                                </p>
                                <h2 class="mt-1 text-2xl font-bold text-primary-dark">{{ $slide['title'] }}</h2>
                                @if ($slide['text'] !== '')
                                    <p class="mt-3 max-w-2xl text-lg leading-9 text-ink/85">{{ $slide['text'] }}</p>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </x-carousel>
            @endif

            @if ($prize !== null)
                <a href="{{ $prize['url'] }}" @navigate($prize['url']) data-prize-card class="prize-card shine group flex flex-col justify-between gap-5 p-6 sm:p-7">
                    <span class="flex items-center justify-between gap-3">
                        <span class="text-base font-semibold text-white/90">{{ __('ui.prize.title') }}</span>
                        <x-icon-tile icon="trophy" variant="glass" tone="sky" size="md" />
                    </span>
                    <span class="block">
                        <span class="block text-5xl leading-none font-black tabular-nums" data-prize-total
                            data-count-to="{{ $prize['total'] }}" data-count-on-load>{{ number_format($prize['total']) }}</span>
                        <span class="mt-2 block text-white/85">{{ __('ui.prize.currency') }}</span>
                    </span>
                    <span class="glass inline-flex items-center justify-between gap-2 rounded-full px-4 py-2 text-sm font-semibold">
                        {{ $prize['title'] }}
                        {{ \App\Support\Icon::svg('arrow-left', 'size-4 shrink-0 transition group-hover:-translate-x-1', ['aria-hidden' => 'true']) }}
                    </span>
                </a>
            @endif
        </div>
    </section>
@endif
