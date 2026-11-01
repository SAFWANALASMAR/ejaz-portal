{{--
    البطاقات الخمس تحت البطل (أمر التصور النهائي، النسخة 63 — البنود R6 وR11 وH2 وH3، وR19 للجوال): مستطيلات أفقية متدرجة
    (.tone-card) بالترتيب: «رؤيتنا ورسالتنا» أعرضها بشريحتين، ثم «أهدافنا»، ثم «قيمنا» بشريحة لكل قيمة، ثم «مبادئنا»، ثم «مبادراتنا»
    بشريحة لكل مبادرة منشورة. على الحاسوب صف واحد بارتفاع نحو 150 بكسل: الأيقونة والعنوان في سطر، والنص سطران على الأكثر.
    وعلى الجوال «رؤيتنا ورسالتنا» بعرض كامل، ثم البقية صف واحد متحرك (x-marquee) يصير شبكة ثابتة من 1024 بكسل (.home-cards-static).
    والدرجات داكنة ثم فاتحة بالتعاقب، مثبتة بـ data-tone لأن البطاقة الأولى خارج الشريط.
    والنصوص من «محتوى الرئيسية»: الرؤية والرسالة (وإلا من «عن الجمعية» المنشورة — HomeShowcase::identity)، والأهداف والمبادئ (ناقصة
    حتى تعتمدها الجمعية)، والقيم من قسمها، والعناوين من «عناوين أقسام الرئيسية».

    $identity: list<array{kind, title, text, icon}>، و$initiatives: Collection<Initiative>، و$content: HomeContent::resolved().
--}}
@php
    $headings = $content['headings'];
    $statements = array_values(array_filter($identity, fn (array $slide): bool => $slide['kind'] !== 'value'));
    $values = array_values(array_filter($identity, fn (array $slide): bool => $slide['kind'] === 'value'));
    $rest = 2 + ($values !== [] ? 1 : 0) + ($initiatives->isNotEmpty() ? 1 : 0);
@endphp

<section id="home-identity" class="home-band" data-band="{{ $band }}" aria-label="{{ __('home.cards.label') }}">
    <div class="home-cards mx-auto max-w-[82.5rem] px-4 pt-5 pb-5 sm:px-7 lg:pt-7 lg:pb-6" style="--home-cards-cols: {{ $rest }}; --home-cards-fr: {{ $rest }}fr">
        <div class="tone-card home-card" data-tone="dark" data-identity-card="vision">
            @if ($statements === [])
                <div class="home-card-body">
                    <h2 class="home-card-head"><x-icon-tile icon="eye" size="sm" class="home-card-icon" />{{ $headings['vision'] }}</h2>
                    <p class="home-card-text">{{ \App\Support\MissingText::shown(__('home.cards.vision_missing')) }}</p>
                </div>
            @else
                <x-carousel :label="$headings['vision'].' — '.$headings['mission']" :count="count($statements)" autoplay :interval="6000" compact class="static" data-identity-slider>
                    @foreach ($statements as $index => $slide)
                        <div data-carousel-item @if ($index === 0) data-active @endif data-kind="{{ $slide['kind'] }}" class="home-card-body">
                            <h2 class="home-card-head"><x-icon-tile :icon="$slide['icon']" size="sm" class="home-card-icon" />{{ $headings[$slide['kind']] }}</h2>
                            <p class="home-card-text">{{ $slide['text'] }}</p>
                        </div>
                    @endforeach
                </x-carousel>
            @endif
        </div>

        <x-marquee id="home-cards-track" :label="__('home.cards.label')" :duration="32" class="home-cards-static">
            <div class="tone-card home-card" data-tone="light" data-identity-card="goals">
                <div class="home-card-body">
                    <h2 class="home-card-head"><x-icon-tile icon="flag" size="sm" class="home-card-icon" />{{ $headings['goals'] }}</h2>
                    <p class="home-card-text">{{ \App\Support\MissingText::shown($content['identity']['goals']) }}</p>
                </div>
            </div>

            @if ($values !== [])
                <div class="tone-card home-card" data-tone="dark" data-identity-card="values">
                    <div class="home-card-body pb-0!">
                        <h2 class="home-card-head"><x-icon-tile icon="heart" size="sm" class="home-card-icon" />{{ $headings['values'] }}</h2>
                    </div>
                    <x-carousel :label="$headings['values']" :count="count($values)" autoplay :interval="4000" compact class="static" data-values-slider>
                        @foreach ($values as $index => $slide)
                            <div data-carousel-item @if ($index === 0) data-active @endif data-kind="value" class="home-card-slide">
                                <span class="home-card-pick">{{ $slide['title'] }}</span>
                                {{-- وصف القيمة ظاهراً للجوال ولوحة المفاتيح وقارئ الشاشة، لا في title وحده --}}
                                @if (filled($slide['text']))
                                    <span data-value-text class="home-card-text line-clamp-1!">{{ $slide['text'] }}</span>
                                @endif
                            </div>
                        @endforeach
                    </x-carousel>
                </div>
            @endif

            <div class="tone-card home-card" data-tone="light" data-identity-card="principles">
                <div class="home-card-body">
                    <h2 class="home-card-head"><x-icon-tile icon="scale" size="sm" class="home-card-icon" />{{ $headings['principles'] }}</h2>
                    <p class="home-card-text">{{ \App\Support\MissingText::shown($content['identity']['principles']) }}</p>
                </div>
            </div>

            @if ($initiatives->isNotEmpty())
                <div class="tone-card home-card" data-tone="dark" data-identity-card="initiatives">
                    <div class="home-card-body pb-0!">
                        <h2 class="home-card-head">
                            <x-icon-tile icon="sparkles" size="sm" class="home-card-icon" />{{ $headings['initiatives'] }}
                            <a href="{{ route('initiatives.index') }}" wire:navigate class="more-link home-card-more">{{ $headings['all_initiatives'] }}</a>
                        </h2>
                    </div>
                    <x-carousel :label="$headings['initiatives']" :count="$initiatives->count()" autoplay :interval="5000" compact class="static" data-initiatives-slider>
                        @foreach ($initiatives as $index => $initiative)
                            <div data-carousel-item @if ($index === 0) data-active @endif class="home-card-slide">
                                <a href="{{ route('initiatives.show', $initiative->slug) }}" wire:navigate class="home-card-pick line-clamp-2 hover:underline">{{ $initiative->name }}</a>
                            </div>
                        @endforeach
                    </x-carousel>
                </div>
            @endif
        </x-marquee>
    </div>
</section>
