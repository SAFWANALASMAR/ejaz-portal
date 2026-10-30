{{--
    مربع الأيقونة (مكوّن واحد للموقع كله — التصور النهائي، صفوان 30 أكتوبر 19:18: «أيقونات وكله» بنمط لوحة الإدارة). ثلاث حالات:
    - gradient (الافتراضية): مربع متدرج بلون من الهوية وأيقونة بيضاء، يرتفع وتدور أيقونته قليلاً مع بطاقته (.group).
      اللون بالترتيب من موضع البطاقة (index): أزرق، بنفسجي، أخضر، كحلي، سماوي، بنفسجي فاتح (App\Support\IconTones)، أو tone صريحاً.
    - glass على الأقسام الداكنة: زجاجي بضبابية وطبقة ملونة خلفه.
    - line: أيقونة خطية بالأزرق في مربع فاتح جداً.
    والأيقونة Phosphor duotone عبر App\Support\Icon. زخرفة، فاسم البطاقة يكفي. size: xs (قائمة الرأس وروابط التذييل) أو sm أو md أو lg أو xl.
--}}
@props(['icon', 'variant' => 'gradient', 'tone' => null, 'index' => 0, 'size' => 'md'])

@php
    $variant = in_array($variant, ['line', 'glass', 'gradient'], true) ? $variant : 'gradient';
    $tone = in_array($tone, \App\Support\IconTones::ORDER, true) ? $tone : \App\Support\IconTones::at((int) $index);
    $iconSize = ['xs' => 'size-3.5', 'sm' => 'size-5', 'md' => 'size-7', 'lg' => 'size-8', 'xl' => 'size-11'][$size] ?? 'size-7';
@endphp

<span data-icon-tile data-variant="{{ $variant }}" data-tone="{{ $tone }}"
    {{ $attributes->class(['icon-tile', 'icon-tile-'.$size]) }} aria-hidden="true">
    {{ \App\Support\Icon::svg($icon, $iconSize.' relative') }}
</span>
