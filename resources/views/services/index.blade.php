{{--
    خدماتنا الإلكترونية (التصور النهائي — Final-Services): الرأس، ثم «اختر الخدمة» بعنوانها بين الخطين، ثم بطاقات بيضاء في
    كل منها مربع أيقونة متدرج بلونه من موضعه واسم الخدمة وكبسولة «ابدأ الطلب»، والبطاقة العريضة (قياس الرضا) أفقية بزرها.
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
                            <a href="{{ route($tile['route']) }}" @navigate(route($tile['route'])) class="card-lift group flex items-center gap-4 px-6 py-5">
                                <x-icon-tile :icon="$tile['icon']" tone="lilac" size="lg" />
                                <span class="flex-1 text-xl font-black text-primary-dark">{{ __($tile['label']) }}</span>
                                <span class="rounded-full bg-primary-dark px-4.5 py-2 text-sm font-extrabold text-white transition group-hover:bg-primary" aria-hidden="true">{{ __('ui.media.survey_cta') }}</span>
                            </a>
                        </li>
                    @else
                        <li>
                            <a href="{{ route($tile['route']) }}" @navigate(route($tile['route'])) class="card-lift group flex h-full flex-col gap-3 px-6 py-6">
                                <x-icon-tile :icon="$tile['icon']" :index="$loop->index" size="lg" />
                                <span class="text-xl font-black text-primary-dark">{{ __($tile['label']) }}</span>
                                <span class="mt-auto self-start rounded-full bg-primary-dark px-4.5 py-2 text-sm font-extrabold text-white transition group-hover:bg-primary" aria-hidden="true">{{ __('ui.services.start') }}</span>
                            </a>
                        </li>
                    @endif
                @endforeach
            </ul>
        </div>
    </section>
</x-layouts.app>
