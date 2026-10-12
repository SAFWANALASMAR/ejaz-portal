{{--
    خدماتنا الإلكترونية (الصورة 06 — اللوحي): العنوان ووصفه في المنتصف، ثم شبكة بطاقات بيضاء،
    لكل بطاقة أيقونة خطية في الأعلى واسم الخدمة تحتها. والبطاقة العريضة بعرض كامل (قياس الرضا).
    ولا تظهر بطاقة خدمة لم تُبنَ وحدتها (config/eservices.php).
--}}
<x-layouts.app :title="__('services.title')" :description="__('services.intro')">
    <section class="bg-surface/60 py-12 sm:py-16">
        <div class="container-page max-w-4xl">
            <div class="text-center">
                <span class="mx-auto block h-1 w-12 rounded-full bg-accent" aria-hidden="true"></span>
                <h1 class="mt-3 text-3xl font-bold text-primary sm:text-4xl">{{ __('services.title') }}</h1>
                <p class="mt-3 text-lg text-muted">{{ __('services.intro') }}</p>
            </div>

            <ul class="mt-10 grid grid-cols-2 gap-4 sm:gap-5 lg:grid-cols-3">
                @foreach ($tiles as $tile)
                    <li @class(['col-span-2 lg:col-span-3' => $tile['wide'] ?? false])>
                        <x-section-tile :href="route($tile['route'])" :icon="$tile['icon']" :label="__($tile['label'])" tone="primary" size="size-10" class="justify-center" />
                    </li>
                @endforeach
            </ul>
        </div>
    </section>
</x-layouts.app>
