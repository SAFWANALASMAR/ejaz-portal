<?php

use Illuminate\Support\Facades\Blade;

/*
| خطافا التخطيط العام (قاعدة التنسيق، 30 أكتوبر 2026 — plans/2026-10-30-work-queue.md): يضيفهما مالك التخطيط فلا يعدّله
| فرع آخر. @stack('head') لما تضعه الصفحة في رأسها (البيانات المنظمة)، والخاصية canonical لصفحة في رابطها سر
| (معرض الشواهد): بلا canonical ولا og:url.
*/

it('keeps the canonical link and og:url by default', function () {
    $html = Blade::render('<x-layouts.app title="عنوان">محتوى</x-layouts.app>');

    expect($html)->toContain('<link rel="canonical" href="'.url()->current().'">')
        ->toContain('<meta property="og:url" content="'.url()->current().'">');
});

it('drops the canonical link and og:url for a page whose link holds a secret', function () {
    $html = Blade::render('<x-layouts.app title="عنوان" :canonical="false">محتوى</x-layouts.app>');

    expect($html)->not->toContain('rel="canonical"')
        ->not->toContain('og:url');
});

it('renders what a page pushes to the head stack inside the head', function () {
    $html = Blade::render("@push('head')<meta name=\"probe\" content=\"1\">@endpush<x-layouts.app title=\"عنوان\">محتوى</x-layouts.app>");

    expect(str($html)->before('</head>')->toString())->toContain('<meta name="probe" content="1">');
});
