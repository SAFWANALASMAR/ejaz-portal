{{--
    شريط العودة (طلب المدير التنفيذي، 29 أكتوبر 2026 — قائمة العمل، البند ٣): في كل صفحة داخلية تحت رأس الموقع، لا الرئيسية.
    «العودة للقائمة السابقة» إلى الأم المنطقية من جدول واحد (App\Support\BackNavigation)، وما لا أم له يعود بتاريخ المتصفح
    (data-history-back — back-nav.js)، ورابطه الرئيسية لمن فتح الصفحة مباشرة. و«العودة للرئيسية» بأيقونة بيت. والرابطان
    يعملان مع التنقّل السريع (wire:navigate) وبلوحة المفاتيح.
--}}
@php
    $route = request()->route();
    $parent = \App\Support\BackNavigation::parentUrl($route);
    $home = route('home');
@endphp

<nav data-back-nav aria-label="{{ __('ui.back_nav.label') }}" {{ $attributes->class(['back-nav']) }}>
    <div class="container-page flex flex-wrap items-center gap-2 py-2.5">
        <a href="{{ $parent ?? $home }}" wire:navigate @if ($parent === null) data-history-back @endif class="back-nav-link group">
            {{-- السهم «للخلف» إلى اليمين في الموقع العربي --}}
            {{ \App\Support\Icon::svg('arrow-right', 'size-4 transition group-hover:translate-x-0.5', ['aria-hidden' => 'true']) }}
            {{ __('ui.back_nav.parent') }}
        </a>
        <a href="{{ $home }}" wire:navigate class="back-nav-link group">
            {{ \App\Support\Icon::svg('home', 'size-4', ['aria-hidden' => 'true']) }}
            {{ __('ui.back_nav.home') }}
        </a>
    </div>
</nav>
