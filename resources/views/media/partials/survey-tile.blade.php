{{-- «قياس الرضا» بعرض كامل كما في الصورة 05، ومتى فُتح استبيان عام (كبطاقة الخدمات)، فلا رابط إلى 404 --}}
<a href="{{ route('surveys.index') }}"
    class="mt-4 flex items-center justify-center gap-3 rounded-2xl border border-line/70 bg-white p-5 font-semibold text-ink shadow-xs transition hover:border-primary/40 hover:shadow-md">
    <span class="text-accent" aria-hidden="true">{{ svg('heroicon-o-chart-bar', 'size-7') }}</span>
    {{ __('media.tiles.surveys') }}
</a>
