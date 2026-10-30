{{--
    مربع الأيقونة المعتمد (صفوان، 30 أكتوبر 11:58 — plans/2026-10-30-visual-refresh-2-directive.md): مكوّن واحد للموقع كله بحالتين:
    gradient على الخلفيات الفاتحة (مربع متدرج من لون الهوية بأيقونة بيضاء، يرتفع ويدور قليلاً مع بطاقته)، وglass على الأقسام
    الداكنة (زجاجي بضبابية وطبقة ملونة خلفه). والأيقونة Phosphor duotone عبر App\Support\Icon. زخرفة، فاسم البطاقة يكفي.
    tone: blue أو violet أو green أو navy أو sky (ألوان design-system.md). size: sm أو md أو lg أو xl.
--}}
@props(['icon', 'variant' => 'gradient', 'tone' => 'blue', 'size' => 'md'])

@php
    $iconSize = ['sm' => 'size-5', 'md' => 'size-7', 'lg' => 'size-8', 'xl' => 'size-11'][$size] ?? 'size-7';
@endphp

<span data-icon-tile data-variant="{{ $variant === 'glass' ? 'glass' : 'gradient' }}" data-tone="{{ $tone }}"
    {{ $attributes->class(['icon-tile', 'icon-tile-'.$size]) }} aria-hidden="true">
    {{ \App\Support\Icon::svg($icon, $iconSize.' relative') }}
</span>
