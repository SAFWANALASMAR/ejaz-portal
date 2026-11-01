{{--
    تحت البطل ثلاث بطاقات متدرجة بألوان الهوية (المدير التنفيذي، 31 أكتوبر 01:28 — docs/design/director-notes-2026-10-31):
    «رؤيتنا» بشرائح الرؤية ثم الرسالة، و«قيمنا» بشريحة لكل قيمة بأيقونتها، و«مبادراتنا» بشريحة لكل مبادرة منشورة. والجوائز في
    شريحة الإعلانات وصفحة المسابقة. والتبديل التلقائي لطيف (x-carousel compact) يتوقف بالمرور والتركيز وزر الإيقاف، ولا يعمل
    لمن طلب تقليل الحركة. والنص أبيض على تدرجات داكنة (AA — tests/Unit/IconTileContrastTest.php).

    $identity: list<array{kind, title, text, icon}> (HomeShowcase::identity)، و$initiatives: Collection<Initiative>.
--}}
@php
    $statements = array_values(array_filter($identity, fn (array $slide): bool => $slide['kind'] !== 'value'));
    $values = array_values(array_filter($identity, fn (array $slide): bool => $slide['kind'] === 'value'));
    $cards = count(array_filter([$statements !== [], $values !== [], $initiatives->isNotEmpty()]));
@endphp

@if ($cards > 0)
    <section id="home-identity" class="home-band" data-band="1" aria-label="{{ __('ui.identity.label') }}">
        <div @class(['mx-auto grid max-w-[82.5rem] gap-5 px-4 pt-8 pb-10 sm:px-7', 'lg:grid-cols-3' => $cards === 3, 'lg:grid-cols-2' => $cards === 2])>
            @if ($statements !== [])
                <div class="identity-card group" data-tone="blue" data-identity-card="vision">
                    <h2 class="identity-card-title">
                        <x-icon-tile icon="eye" variant="glass" size="sm" />
                        {{ __('ui.identity.our_vision') }}
                    </h2>
                    <x-carousel :label="__('ui.identity.statements')" :count="count($statements)" autoplay :interval="6000" compact class="static" data-identity-slider>
                        @foreach ($statements as $index => $slide)
                            <div data-carousel-item @if ($index === 0) data-active @endif data-kind="{{ $slide['kind'] }}" class="pt-3 pb-10">
                                <span class="block text-sm font-bold text-white/85">{{ $slide['title'] }}</span>
                                <p class="mt-1 text-[17px] leading-[1.8] text-white">{{ $slide['text'] }}</p>
                            </div>
                        @endforeach
                    </x-carousel>
                </div>
            @endif

            @if ($values !== [])
                <div class="identity-card group" data-tone="ocean" data-identity-card="values">
                    <h2 class="identity-card-title">
                        <x-icon-tile icon="heart" variant="glass" size="sm" />
                        {{ __('ui.identity.our_values') }}
                    </h2>
                    <x-carousel :label="__('ui.identity.values')" :count="count($values)" autoplay :interval="4000" compact class="static" data-values-slider>
                        @foreach ($values as $index => $slide)
                            <div data-carousel-item @if ($index === 0) data-active @endif data-kind="value" class="pt-3 pb-10">
                                <span class="flex items-center gap-2 text-[1.375rem] font-extrabold text-white">
                                    {{ \App\Support\Icon::svg($slide['icon'], 'size-6 shrink-0', ['aria-hidden' => 'true']) }}
                                    {{ $slide['title'] }}
                                </span>
                                {{-- وصف القيمة من الخطة الاستراتيجية ظاهراً للجوال ولوحة المفاتيح وقارئ الشاشة، لا في title وحده --}}
                                @if (filled($slide['text']))
                                    <span data-value-text class="mt-1 line-clamp-2 block text-sm leading-6 text-white/90">{{ $slide['text'] }}</span>
                                @endif
                            </div>
                        @endforeach
                    </x-carousel>
                </div>
            @endif

            @if ($initiatives->isNotEmpty())
                <div class="identity-card group" data-tone="deep" data-identity-card="initiatives">
                    <h2 class="identity-card-title">
                        <x-icon-tile icon="sparkles" variant="glass" size="sm" />
                        {{ __('ui.identity.our_initiatives') }}
                    </h2>
                    <x-carousel :label="__('ui.identity.our_initiatives')" :count="$initiatives->count()" autoplay :interval="5000" compact class="static" data-initiatives-slider>
                        @foreach ($initiatives as $index => $initiative)
                            <div data-carousel-item @if ($index === 0) data-active @endif class="pt-3 pb-10">
                                <a href="{{ route('initiatives.show', $initiative->slug) }}" wire:navigate class="text-[1.375rem] font-extrabold text-white hover:underline">{{ $initiative->name }}</a>
                                @if (filled($initiative->summary))
                                    <span class="mt-1 line-clamp-2 block text-sm leading-6 text-white/90">{{ $initiative->summary }}</span>
                                @endif
                            </div>
                        @endforeach
                    </x-carousel>
                    <a href="{{ route('initiatives.index') }}" wire:navigate class="identity-card-more">{{ __('ui.identity.all_initiatives') }}</a>
                </div>
            @endif
        </div>
    </section>
@endif
