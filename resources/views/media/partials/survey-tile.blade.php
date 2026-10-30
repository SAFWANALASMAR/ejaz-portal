{{-- «قياس الرضا» بعرض كامل كما في الصورة 05، ومتى فُتح استبيان عام (كبطاقة الخدمات)، فلا رابط إلى 404 --}}
<a href="{{ route('surveys.index') }}" wire:navigate
    class="card-lift group mt-4 flex items-center justify-center gap-3 p-5 font-semibold text-ink">
    <x-icon-tile icon="chart-bar" tone="green" size="md" />
    {{ __('media.tiles.surveys') }}
</a>
