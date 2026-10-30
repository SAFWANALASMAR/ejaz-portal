{{--
    خدماتنا الإلكترونية (الصورة 06 — اللوحي): العنوان ووصفه في المنتصف، ثم شبكة بطاقات بيضاء،
    لكل بطاقة أيقونة خطية في الأعلى واسم الخدمة تحتها. والبطاقة العريضة بعرض كامل (قياس الرضا).
    ولا تظهر بطاقة خدمة لم تُبنَ وحدتها (config/eservices.php).
--}}
<x-layouts.app :title="__('services.title')" :description="__('services.intro')">
    <x-page-hero :title="__('services.title')" :summary="__('services.intro')" />

    <section class="mesh-light py-12 sm:py-16">
        <div class="container-page max-w-4xl">
            <ul class="grid grid-cols-2 gap-4 sm:gap-5 lg:grid-cols-3" data-reveal-group>
                @foreach ($tiles as $tile)
                    <li @class(['col-span-2 lg:col-span-3' => $tile['wide'] ?? false])>
                        <x-section-tile :index="$loop->index" :href="route($tile['route'])" :icon="$tile['icon']" :label="__($tile['label'])" />
                    </li>
                @endforeach
            </ul>
        </div>
    </section>
</x-layouts.app>
