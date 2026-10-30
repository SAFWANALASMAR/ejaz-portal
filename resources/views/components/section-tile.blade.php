{{--
    بلاطة قسم: أيقونة في مربع مستدير بتدرج خفيف من ألوان الهوية (x-icon-tile)، ثم الاسم، في بطاقة بيضاء بظل ناعم ترتفع عند
    المرور وتميل قليلاً مع الفأرة (التحسين البصري — docs/plans/visual-refresh.md). وtone باقٍ لمن يمرره ولا يغيّر الشكل.
--}}
@props(['href', 'icon', 'label', 'tone' => 'primary', 'size' => 'size-8'])

<a href="{{ $href }}" @navigate($href)
    {{ $attributes->class(['card-glow tilt group flex h-full flex-col items-center gap-4 p-6 text-center']) }}>
    <x-icon-tile :icon="$icon" :tone="['primary' => 'blue', 'accent' => 'violet', 'secondary' => 'green'][$tone] ?? $tone" size="lg" />
    <span class="font-semibold text-ink group-hover:text-primary">{{ $label }}</span>
</a>
