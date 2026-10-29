{{-- «قياس الرضا» بعرض كامل كما في الصورة 05، ومتى فُتح استبيان عام (كبطاقة الخدمات)، فلا رابط إلى 404 --}}
<a href="{{ route('surveys.index') }}" wire:navigate
    class="card-lift group mt-4 flex items-center justify-center gap-3 p-5 font-semibold text-ink">
    <span class="icon-tile size-12" aria-hidden="true">{{ \App\Support\Icon::svg('chart-bar', 'size-7') }}</span>
    {{ __('media.tiles.surveys') }}
</a>
