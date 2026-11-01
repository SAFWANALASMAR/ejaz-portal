{{--
    مربع الأيقونة الموحد (.gt في التصور النهائي، النسخة 63 — البندان N2 وR5): مكوّن واحد للموقع كله. ثلاث حالات:
    - gradient (الافتراضية): مربع مستدير بتدرج أزرق، ودرجته من موضع بطاقته في الصفحة بـ CSS وحده (nth-child في concept.css):
      فاتحة جداً، ثم فاتحة (الرسم #1F4565)، ثم متوسطة، ثم غامقة (الرسم أبيض). يرتفع ويميل مع بطاقته (.group) أو رابطه.
      وshade يثبّت درجة بعينها (pale أو light أو medium أو dark — App\Support\IconTones).
    - glass على الأقسام الداكنة: زجاجي بضبابية وطبقة زرقاء خلفه، والرسم أبيض.
    - line: أيقونة بالأزرق في مربع فاتح جداً بحد رفيع.
    والرسم Phosphor duotone عبر App\Support\Icon. زخرفة، فاسم البطاقة يكفي. size: xs (القائمة وروابط التذييل) أو sm أو md أو lg أو xl.
    وtone اسم سابق لـ shade، وindex لم يعد يحدد الدرجة (يبقى في الخصائص فلا يتسرب سمةً إلى الصفحة).
--}}
@props(['icon', 'variant' => 'gradient', 'shade' => null, 'tone' => null, 'index' => 0, 'size' => 'md'])

@php
    $variant = in_array($variant, ['line', 'glass', 'gradient'], true) ? $variant : 'gradient';
    $shade = $shade ?? $tone;
    $shade = in_array($shade, \App\Support\IconTones::ORDER, true) ? $shade : null;
    $iconSize = ['xs' => 'size-3.5', 'sm' => 'size-5', 'md' => 'size-7', 'lg' => 'size-8', 'xl' => 'size-11'][$size] ?? 'size-7';
@endphp

<span data-icon-tile data-variant="{{ $variant }}" @if ($shade) data-shade="{{ $shade }}" @endif
    {{ $attributes->class(['icon-tile gt', 'icon-tile-'.$size]) }} aria-hidden="true">
    {{ \App\Support\Icon::svg($icon, $iconSize.' relative') }}
</span>
