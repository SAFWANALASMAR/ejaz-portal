{{--
    شريط العودة (طلب المدير التنفيذي، 29 أكتوبر 2026، وموضعه في التصور النهائي: أعلى رأس الصفحة): «العودة للقائمة السابقة» إلى
    الأم المنطقية من جدول واحد (App\Support\BackNavigation)، وما لا أم له يعود بتاريخ المتصفح (data-history-back — back-nav.js)،
    ورابطه الرئيسية لمن فتح الصفحة مباشرة. و«العودة للرئيسية» بأيقونة بيت. والرابطان بلوحة المفاتيح ومع التنقّل السريع.
    inline: داخل رأس الصفحة (x-page-hero) كبسولتين بإطار أبيض. وإلا شريط داكن تحت رأس الموقع لصفحة بلا رأس (الحساب والدخول).
    ويُعلَّم الطلب حين يُرسم، فلا يرسمه التخطيط مرة أخرى.
--}}
@props(['inline' => false])

@php
    $route = request()->route();
    $parent = \App\Support\BackNavigation::parentUrl($route);
    $home = route('home');
@endphp

<nav data-back-nav aria-label="{{ __('ui.back_nav.label') }}" {{ $attributes->class(['back-nav' => ! $inline]) }}>
    <div @class(['flex flex-wrap items-center gap-2.5', 'container-page py-2.5' => ! $inline])>
        {{-- الرجوع بالتاريخ بلا wire:navigate: Livewire يبدأ تنقّله قبل click فيسبق الرجوع --}}
        <a href="{{ $parent ?? $home }}" @if ($parent === null) data-history-back @else wire:navigate @endif class="hero-pill group">
            {{-- السهم «للخلف» إلى اليمين في الموقع العربي --}}
            {{ \App\Support\Icon::svg('chevron-right', 'size-3.5', ['aria-hidden' => 'true']) }}
            {{ __('ui.back_nav.parent') }}
        </a>
        <a href="{{ $home }}" wire:navigate class="hero-pill group">
            {{ \App\Support\Icon::svg('home', 'size-3.5', ['aria-hidden' => 'true']) }}
            {{ __('ui.back_nav.home') }}
        </a>
    </div>
</nav>
