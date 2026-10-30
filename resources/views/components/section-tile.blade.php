{{--
    بلاطة قسم (التصور النهائي — بلاطات المكتبة والمركز الإعلامي والخدمات): بطاقة بيضاء ترتفع وتلمع، فيها مربع أيقونة متدرج
    بلونه من موضعه (index — App\Support\IconTones) بجانب اسم القسم. وtone وsize باقيان لمن يمررهما: tone يثبّت اللون.
--}}
@props(['href', 'icon', 'label', 'tone' => null, 'size' => null, 'index' => 0])

<a href="{{ $href }}" @navigate($href)
    {{ $attributes->class(['card-glow group flex h-full items-center gap-4 px-5 py-4.5']) }}>
    <x-icon-tile :icon="$icon" :tone="['primary' => 'blue', 'accent' => 'violet', 'secondary' => 'green'][$tone] ?? $tone" :index="$index" />
    <span class="text-xl font-black text-primary-dark">{{ $label }}</span>
</a>
