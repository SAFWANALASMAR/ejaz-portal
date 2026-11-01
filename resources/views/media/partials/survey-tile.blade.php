{{-- «قياس الرضا» بعرض كامل كما في الصورة 05، ومتى فُتح استبيان عام (كبطاقة الخدمات)، فلا رابط إلى 404 --}}
{{-- بلغة التصور النهائي (Final-Media): الأيقونة والاسم في جهة البداية، وكبسولة «شارك» في جهة النهاية --}}
<a href="{{ route('surveys.index') }}" wire:navigate
    class="card-lift group mt-4 flex items-center gap-4 px-5 py-4">
    <x-icon-tile icon="chart-bar" shade="light" size="md" />
    <span class="flex-1 text-lg font-black text-primary-dark">{{ __('media.tiles.surveys') }}</span>
    <span class="rounded-full bg-primary-dark px-4 py-1.5 text-sm font-extrabold text-white transition group-hover:bg-primary" aria-hidden="true">{{ __('ui.media.survey_cta') }}</span>
</a>
