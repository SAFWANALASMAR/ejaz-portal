{{-- زر «تحليل» في لوحة الإحصائيات إلى صفحة التحليل (StatsAnalysisPage) --}}
<x-filament-widgets::widget>
    <x-filament::section :heading="__('analysis.title')" :description="__('analysis.subheading')">
        <x-filament::button tag="a" :href="$url" icon="heroicon-o-sparkles">
            {{ __('analysis.analyze') }}
        </x-filament::button>
    </x-filament::section>
</x-filament-widgets::widget>
