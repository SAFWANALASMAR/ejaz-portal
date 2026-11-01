{{--
    «خدماتنا» و«كل ما تحتاجه في بوابة واحدة» بلا فجوة (أمر التصور النهائي، النسخة 63 — البندان H4 وR4): صف واحد متحرك (x-marquee) يظهر
    ثلاث بطاقات أو أربعاً بعرض 226 بكسل، بطاقات ملونة متعاقبة (.tone-card) لكل منها الاسم ومربع أيقونته في سطر، ثم الوصف، ثم «ادخل».
    وبجانبه بطاقة صورة إلى «عن الجمعية» «نحو فهم أعمق لإعجاز القرآن والسنة»، ونصاها من «محتوى الرئيسية» (identity). وعلى الجوال
    الصف المتحرك ثم بطاقة الصورة تحته. بطاقة لكل قسم مبني فقط (BuildHomePage::SHORTCUTS)، ومنها الدورات والمسابقات والمبادرات والشركاء.
--}}
@php
    $headings = $content['headings'];
    $sideTitle = trim((string) ($content['identity']['side_title'] ?? ''));
    $sideText = (string) \App\Support\MissingText::shown($content['identity']['side_text'] ?? null);
    $side = $sideTitle !== '';
    // رابط إلى «عن الجمعية» متى نُشرت وحدها، وإلا بطاقة بلا رابط فلا يفتح الزائر صفحة 404
    $sideLink = Route::has('about') && \App\Models\Page::isPublishedSlug(\App\Models\Page::ABOUT);
    $sideTag = $sideLink ? 'a' : 'div';
@endphp

<section class="home-band" data-band="{{ $band }}" aria-labelledby="home-shortcuts">
    <div class="mx-auto max-w-[82.5rem] px-4 pt-6 pb-7 sm:px-7 lg:pt-7 lg:pb-8">
        <x-section-heading id="home-shortcuts" center icon="squares-2x2" :description="$headings['services_intro']" class="mb-4 gap-0!">{{ $headings['services'] }}</x-section-heading>

        <div @class(['grid items-center gap-4 lg:gap-5', 'lg:grid-cols-[3fr_1fr]' => $side])>
            <x-marquee id="home-services-track" :label="$headings['services']" :duration="max(24, count($shortcuts) * 4)" class="min-w-0">
                @foreach ($shortcuts as $shortcut)
                    <a href="{{ $shortcut['url'] }}" @navigate($shortcut['url']) data-card data-service-card="{{ $shortcut['key'] }}" class="tone-card service-tile group">
                        <span class="flex items-center justify-between gap-3">
                            <span class="service-tile-title">{{ $shortcut['title'] }}</span>
                            <x-icon-tile :icon="$shortcut['icon']" size="md" class="service-tile-icon" />
                        </span>
                        <span class="service-tile-text tone-card-muted">{{ $shortcut['text'] }}</span>
                        <span class="service-tile-enter">{{ __('ui.services.enter') }}</span>
                    </a>
                @endforeach
            </x-marquee>

            @if ($side)
                <{{ $sideTag }} @if ($sideLink) href="{{ route('about') }}" wire:navigate @endif data-side-card class="group relative flex min-h-[15rem] flex-col justify-end overflow-hidden rounded-[1.25rem] p-5 text-white lg:min-h-[15.625rem] lg:p-6">
                    <img src="{{ $content['rationale']['image'] }}" alt="" loading="lazy" width="768" height="512"
                        class="absolute inset-0 size-full object-cover transition duration-700 group-hover:scale-105">
                    <span class="absolute inset-0 bg-linear-to-b from-primary-dark/15 from-20% to-primary-deep/90 to-80%" aria-hidden="true"></span>
                    <span class="relative text-2xl leading-[1.3] font-black lg:text-[1.75rem]">{{ $sideTitle }}</span>
                    @if ($sideText !== '')
                        <span class="relative text-sm text-[var(--concept-pale)] lg:text-base">{{ $sideText }}</span>
                    @endif
                </{{ $sideTag }}>
            @endif
        </div>
    </div>
</section>
