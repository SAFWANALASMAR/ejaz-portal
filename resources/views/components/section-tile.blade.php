{{-- بلاطة قسم: أيقونة واسم في بطاقة بيضاء (المرجع البصري 05). ولون الأيقونة وحجمها يختلفان بين الصفحات --}}
@props(['href', 'icon', 'label', 'tone' => 'accent', 'size' => 'size-9'])

<a href="{{ $href }}" @navigate($href)
    {{ $attributes->class(['flex h-full flex-col items-center gap-3 rounded-2xl border border-line/70 bg-white p-6 text-center shadow-xs transition hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-md']) }}>
    <span @class(['text-accent' => $tone === 'accent', 'text-primary' => $tone === 'primary']) aria-hidden="true">{{ svg('heroicon-o-'.$icon, $size) }}</span>
    <span class="font-semibold text-ink">{{ $label }}</span>
</a>
