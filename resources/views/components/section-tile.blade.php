{{--
    بلاطة قسم: أيقونة في مربع مستدير بلون الهوية، ثم الاسم، في بطاقة بيضاء (المرجع البصري 05). شكل واحد للأيقونات في كل
    الصفحات (ملاحظات المدير التنفيذي، 25 أكتوبر 2026 — التجميل): الأزرق الفاتح، ويمتلئ بالأزرق عند المرور. وtone باقٍ
    لمن يمرره ولا يغيّر الشكل.
--}}
@props(['href', 'icon', 'label', 'tone' => 'primary', 'size' => 'size-8'])

<a href="{{ $href }}" @navigate($href)
    {{ $attributes->class(['group flex h-full flex-col items-center gap-3 rounded-2xl border border-line/70 bg-white p-6 text-center shadow-xs transition hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-md']) }}>
    <span class="inline-flex size-16 items-center justify-center rounded-2xl bg-primary-soft text-primary transition group-hover:bg-primary group-hover:text-white" aria-hidden="true">
        {{ svg('heroicon-o-'.$icon, $size) }}
    </span>
    <span class="font-semibold text-ink">{{ $label }}</span>
</a>
