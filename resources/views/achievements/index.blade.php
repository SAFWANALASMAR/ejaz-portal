{{--
    «إنجازاتنا» العامة (docs/decisions/2026-11-02-achievements.md): رأس الصفحة الداخلية، ثم الخط الزمني للمنشور وحده بالأحدث
    (x-achievement-timeline)، ومرفق كل إنجاز برابطه المؤقت الموقّع. وبلا منشور حالة فارغة.
--}}
<x-layouts.app :title="__('achievements.title')">
    <x-page-hero :title="__('achievements.title')" icon="star" />

    <section class="py-10 sm:py-14">
        <div class="container-page max-w-4xl">
            <x-section-heading icon="star">{{ __('achievements.timeline') }}</x-section-heading>

            @if ($achievements->isEmpty())
                <x-empty-state icon="star" :message="__('achievements.empty')" class="mt-8" />
            @else
                <x-achievement-timeline :achievements="$achievements" class="mt-8" />

                {{ $achievements->links('partials.pagination') }}
            @endif
        </div>
    </section>
</x-layouts.app>
