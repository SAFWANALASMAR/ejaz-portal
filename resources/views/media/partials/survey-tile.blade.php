{{-- «قياس الرضا» بعرض كامل ومتى فُتح استبيان عام (كبطاقة الخدمات)، فلا رابط إلى 404. بلغة التصور النهائي (Final-Media، المرحلة ج):
     بطاقة ملونة داكنة، الأيقونة والاسم في جهة البداية، وكبسولة «شارك» في جهة النهاية --}}
<a href="{{ route('surveys.index') }}" wire:navigate data-tone="dark" data-survey-tile
    class="tone-card group mt-3.5 flex items-center gap-4 px-5 py-4">
    <x-icon-tile icon="chart-bar" size="md" />
    <span class="flex-1 text-lg font-black">{{ __('media.tiles.surveys') }}</span>
    <span class="tone-pill" aria-hidden="true">{{ __('ui.media.survey_cta') }}</span>
</a>
