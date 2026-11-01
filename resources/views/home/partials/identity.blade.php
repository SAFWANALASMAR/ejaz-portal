{{--
    البطاقات الخمس تحت البطل (أمر التصور النهائي، النسخة 63 — البنود R6 وR11 وH2 وH3، وR19 للجوال؛ وملاحظات المدير التنفيذي، 1 نوفمبر
    2026، البنود ٨ و٩ و١٠ و٢٧): بطاقات متدرجة (.tone-card) كما في اللوحة الأولى من ملف التصور: صف من ثلاث على الحاسوب («رؤيتنا ورسالتنا»
    بشريحتين، ثم «أهدافنا»، ثم «قيمنا» بشريحة لكل قيمة)، ثم صف من اثنتين عريضتين («مبادئنا»، ثم «مبادراتنا» بشريحة لكل مبادرة منشورة
    ورابط «الكل»). وعلى الجوال «رؤيتنا ورسالتنا» بعرض كامل، ثم البقية صف متحرك (x-marquee).

    - كل بطاقة رابط إلى صفحتها أو قسمها (البند ٨): الرؤية والرسالة والأهداف والقيم والمبادئ إلى مقطعها في «عن الجمعية» متى نُشرت
      (#about-vision وأخواتها — content/blocks)، والمبادرات إلى صفحتها. والرابط يغطي البطاقة، والنقاط وزر الإيقاف وروابط الشرائح فوقه.
    - الشرائح تتبدل تلقائياً حتى لمن طلب تقليل الحركة (تبديل فوري بلا لمعة ولا انتقال — carousel.js)، ولكل شريحة قيمة أو مبادرة
      أيقونتها الملونة بدرجات الأزرق، ولمعة خفيفة عند التبدل لمن لم يطلب تقليلها (البند ١٠).
    - الدرجات داكنة ثم فاتحة بالتعاقب، مثبتة بـ data-tone. والنصوص من «محتوى الرئيسية» والقيم من قسمها، والعناوين من «عناوين أقسام الرئيسية».

    $identity: list<array{kind, title, text, icon}>، و$initiatives: Collection<Initiative>، و$content: HomeContent::resolved().
--}}
@php
    $headings = $content['headings'];
    $statements = array_values(array_filter($identity, fn (array $slide): bool => $slide['kind'] !== 'value'));
    $values = array_values(array_filter($identity, fn (array $slide): bool => $slide['kind'] === 'value'));
    // مقاطع «عن الجمعية» متى نُشرت، وإلا بلا رابط فلا يفتح الزائر صفحة 404
    $about = Route::has('about') && \App\Models\Page::isPublishedSlug(\App\Models\Page::ABOUT);
    $aboutLink = fn (string $section): ?string => $about ? route('about').'#about-'.$section : null;
    // أيقونات المبادرات بموضعها (زخرفة لا محتوى)، كما في «عن الجمعية» والتصور
    $initiativeIcons = ['heart', 'arrow-trending-up', 'cube', 'light-bulb', 'arrow-up'];
@endphp

<section id="home-identity" class="home-band" data-band="{{ $band }}" aria-label="{{ __('home.cards.label') }}">
    <div class="home-cards mx-auto max-w-[82.5rem] px-4 pt-5 pb-5 sm:px-7 lg:pt-7 lg:pb-6">
        <div class="tone-card home-card" data-tone="dark" data-identity-card="vision">
            @if ($link = $aboutLink('vision'))
                <a href="{{ $link }}" wire:navigate class="home-card-link" data-identity-link><span class="sr-only">{{ $headings['vision'] }}</span></a>
            @endif
            @if ($statements === [])
                <div class="home-card-body">
                    <h2 class="home-card-head"><x-icon-tile icon="eye" size="sm" class="home-card-icon" />{{ $headings['vision'] }}</h2>
                    <p class="home-card-text">{{ \App\Support\MissingText::shown(__('home.cards.vision_missing')) }}</p>
                </div>
            @else
                <x-carousel :label="$headings['vision'].' — '.$headings['mission']" :count="count($statements)" autoplay :interval="6000" compact class="static" data-identity-slider data-autoplay-always>
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
                @if ($link = $aboutLink('goals'))
                    <a href="{{ $link }}" wire:navigate class="home-card-link" data-identity-link><span class="sr-only">{{ $headings['goals'] }}</span></a>
                @endif
                <div class="home-card-body">
                    <h2 class="home-card-head"><x-icon-tile icon="flag" size="sm" class="home-card-icon" />{{ $headings['goals'] }}</h2>
                    <p class="home-card-text">{{ \App\Support\MissingText::shown($content['identity']['goals']) }}</p>
                </div>
            </div>

            @if ($values !== [])
                <div class="tone-card home-card" data-tone="dark" data-identity-card="values">
                    @if ($link = $aboutLink('values'))
                        <a href="{{ $link }}" wire:navigate class="home-card-link" data-identity-link><span class="sr-only">{{ $headings['values'] }}</span></a>
                    @endif
                    <div class="home-card-body pb-0!">
                        <h2 class="home-card-head"><x-icon-tile icon="heart" size="sm" class="home-card-icon" />{{ $headings['values'] }}</h2>
                    </div>
                    <x-carousel :label="$headings['values']" :count="count($values)" autoplay :interval="4000" compact class="static" data-values-slider data-autoplay-always>
                        @foreach ($values as $index => $slide)
                            <div data-carousel-item @if ($index === 0) data-active @endif data-kind="value" class="home-card-slide">
                                <span class="home-card-pick"><x-icon-tile :icon="$slide['icon']" size="xs" class="home-card-pick-icon" data-pick-icon />{{ $slide['title'] }}</span>
                                {{-- وصف القيمة ظاهراً للجوال ولوحة المفاتيح وقارئ الشاشة، لا في title وحده --}}
                                @if (filled($slide['text']))
                                    <span data-value-text class="home-card-text line-clamp-1!">{{ $slide['text'] }}</span>
                                @endif
                            </div>
                        @endforeach
                    </x-carousel>
                </div>
            @endif

            <div class="tone-card home-card home-card-wide" data-tone="light" data-identity-card="principles">
                @if ($link = $aboutLink('principles'))
                    <a href="{{ $link }}" wire:navigate class="home-card-link" data-identity-link><span class="sr-only">{{ $headings['principles'] }}</span></a>
                @endif
                <div class="home-card-body">
                    <h2 class="home-card-head"><x-icon-tile icon="scale" size="sm" class="home-card-icon" />{{ $headings['principles'] }}</h2>
                    <p class="home-card-text">{{ \App\Support\MissingText::shown($content['identity']['principles']) }}</p>
                </div>
            </div>

            @if ($initiatives->isNotEmpty())
                <div class="tone-card home-card home-card-wide" data-tone="dark" data-identity-card="initiatives">
                    {{-- رابط البطاقة للفأرة واللمس، ولوحة المفاتيح وقارئ الشاشة يصلان الوجهة نفسها من «الكل» فلا تقف مرتين --}}
                    <a href="{{ route('initiatives.index') }}" wire:navigate class="home-card-link" data-identity-link tabindex="-1" aria-hidden="true"></a>
                    <div class="home-card-body pb-0!">
                        <h2 class="home-card-head">
                            <x-icon-tile icon="sparkles" size="sm" class="home-card-icon" />{{ $headings['initiatives'] }}
                            <a href="{{ route('initiatives.index') }}" wire:navigate class="more-link home-card-more">{{ $headings['all_initiatives'] }}</a>
                        </h2>
                    </div>
                    {{-- كل المبادرات المنشورة تتبدل (البند ٩) --}}
                    <x-carousel :label="$headings['initiatives']" :count="$initiatives->count()" autoplay :interval="3500" compact class="static" data-initiatives-slider data-autoplay-always>
                        @foreach ($initiatives as $index => $initiative)
                            <div data-carousel-item @if ($index === 0) data-active @endif class="home-card-slide" data-initiative-slide>
                                <a href="{{ route('initiatives.show', $initiative->slug) }}" wire:navigate class="home-card-pick line-clamp-2 hover:underline">
                                    <x-icon-tile :icon="$initiativeIcons[$index % count($initiativeIcons)]" size="xs" class="home-card-pick-icon" data-pick-icon />{{ $initiative->name }}
                                </a>
                            </div>
                        @endforeach
                    </x-carousel>
                </div>
            @endif
        </x-marquee>
    </div>
</section>
