{{--
    والبطاقات منذ إضافة توجيه المدير (3 نوفمبر 2026، البند ٢٩) بالنمط «أ» من x-service-card.
    خدماتنا الإلكترونية (التصور النهائي — Final-Services): الرأس، ثم «اختر الخدمة» بعنوانها بين الخطين، ثم بطاقات ملونة تتعاقب
    درجتها (المرحلة ج — البند P8)، في كل منها مربع الأيقونة واسم الخدمة وكبسولة «ابدأ الطلب»، وأولها الدورات والبرامج والمسابقات
    والمبادرات (البند R4)، والبطاقة العريضة (قياس الرضا) أفقية بزرها.
    ولا تظهر بطاقة خدمة لم تُبنَ وحدتها (config/eservices.php).
--}}
<x-layouts.app :title="__('services.title')" :description="__('services.intro')">
    <x-page-hero :title="__('services.title')" :summary="__('services.intro')" />
    <x-page-sections />

    <section class="band-sky">
        <div class="mx-auto max-w-[82.5rem] px-4 pt-11 pb-14 sm:px-7">
            <x-section-heading center :description="__('ui.services.choose_hint')" class="mb-6.5">{{ __('ui.services.choose') }}</x-section-heading>

            {{-- بطاقات بالنمط «أ» (إضافة توجيه المدير، 3 نوفمبر 2026، البند ٢٩ — x-service-card): ثلاثة أعمدة بلا خانة فارغة، وعمودان على
                 820 فأقل وعمود على 480 فأقل. والأخيرة في الثلاثة تمتد بما بقي من الصف، وفي العمودين في المنتصف بعرض بطاقة لا ممتدة --}}
            <ul class="service-grid" data-service-grid data-reveal-group>
                @foreach ($tiles as $tile)
                    <li>
                        <x-service-card :href="route($tile['route'])" :title="__($tile['label'])" :icon="$tile['symbol'] ?? ''" :fallback="$tile['icon']" />
                    </li>
                @endforeach
                {{-- متجر الجمعية على سلة بتبويب جديد، ولا يظهر بلا رابط صالح (1 نوفمبر 2026) --}}
                @if ($storeLink = \App\Rules\StoreUrl::link())
                    <li data-store-card>
                        <x-service-card :href="$storeLink" :title="__('ui.services.store')" icon="store" fallback="shopping-bag" external />
                    </li>
                @endif
            </ul>
        </div>
    </section>
</x-layouts.app>
