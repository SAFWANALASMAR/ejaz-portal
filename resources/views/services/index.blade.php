{{--
    خدماتنا الإلكترونية (التصور النهائي — Final-Services): الرأس، ثم «اختر الخدمة» بعنوانها بين الخطين، ثم بطاقات ملونة تتعاقب
    درجتها (المرحلة ج — البند P8)، في كل منها مربع الأيقونة واسم الخدمة وكبسولة «ابدأ الطلب»، وأولها الدورات والبرامج والمسابقات
    والمبادرات (البند R4)، والبطاقة العريضة (قياس الرضا) أفقية بزرها.
    ولا تظهر بطاقة خدمة لم تُبنَ وحدتها (config/eservices.php).
--}}
<x-layouts.app :title="__('services.title')" :description="__('services.intro')">
    <x-page-hero :title="__('services.title')" :summary="__('services.intro')" />

    <section class="band-sky">
        <div class="mx-auto max-w-[82.5rem] px-4 pt-11 pb-14 sm:px-7">
            <x-section-heading center :description="__('ui.services.choose_hint')" class="mb-6.5">{{ __('ui.services.choose') }}</x-section-heading>

            <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3" data-reveal-group>
                @foreach ($tiles as $tile)
                    @if ($tile['wide'] ?? false)
                        <li class="sm:col-span-2 lg:col-span-3">
                            <a href="{{ route($tile['route']) }}" @navigate(route($tile['route'])) class="tone-card group flex items-center gap-4 px-6 py-5" data-tone="dark">
                                <x-icon-tile :icon="$tile['icon']" size="lg" />
                                <span class="flex-1 text-xl font-black">{{ __($tile['label']) }}</span>
                                <span class="tone-pill" aria-hidden="true">{{ __('ui.media.survey_cta') }}</span>
                            </a>
                        </li>
                    @else
                        <li class="tone-card">
                            <a href="{{ route($tile['route']) }}" @navigate(route($tile['route'])) class="group flex h-full flex-col gap-3 px-6 py-6">
                                <x-icon-tile :icon="$tile['icon']" size="lg" />
                                <span class="text-xl font-black">{{ __($tile['label']) }}</span>
                                <span class="tone-pill mt-auto self-start" aria-hidden="true">{{ __('ui.services.start') }}</span>
                            </a>
                        </li>
                    @endif
                @endforeach
                {{-- متجر الجمعية على سلة بتبويب جديد، ولا يظهر بلا رابط صالح (1 نوفمبر 2026) --}}
                @if ($storeLink = \App\Rules\StoreUrl::link())
                    <li class="tone-card" data-store-card>
                        <a href="{{ $storeLink }}" target="_blank" rel="noopener noreferrer" class="group flex h-full flex-col gap-3 px-6 py-6">
                            <x-icon-tile icon="shopping-bag" size="lg" />
                            <span class="text-xl font-black">{{ __('ui.services.store') }}</span>
                            <span class="sr-only">({{ __('site.opens_in_new_tab') }})</span>
                            <span class="tone-pill mt-auto self-start" aria-hidden="true">{{ __('ui.services.store_open') }}</span>
                        </a>
                    </li>
                @endif
            </ul>
        </div>
    </section>
</x-layouts.app>
