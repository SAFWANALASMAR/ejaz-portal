{{--
    شعار منصة التواصل من resources/svg/social (Simple Icons، رخصة CC0 — decisions.md، 25 أكتوبر 2026)، بلون النص. الاسم
    من قائمة المنصات المسموحة وحدها (SiteSettings::SOCIAL)، فلا يُبنى مسار ملف من مدخل حر. زخرفي: اسم المنصة بجانبه.
--}}
@props(['network'])

@php
    $file = in_array($network, \App\Filament\Pages\SiteSettings::SOCIAL, true) ? resource_path('svg/social/'.$network.'.svg') : null;
    $svg = $file !== null && is_file($file) ? (string) file_get_contents($file) : '';
@endphp

@if ($svg !== '')
    {!! str_replace(['<svg role="img" ', '<svg '], ['<svg ', '<svg aria-hidden="true" focusable="false" fill="currentColor" class="'.e($attributes->get('class', 'size-6')).'" '], $svg) !!}
@endif
