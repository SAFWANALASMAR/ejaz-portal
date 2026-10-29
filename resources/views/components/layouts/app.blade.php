{{-- التخطيط العام للموقع: عربي من اليمين، ببيانات المشاركة لكل صفحة --}}
@props([
    'title' => null,
    'description' => null,
    'image' => null,
    'noindex' => false,
])

@php
    $siteName = __('site.name');
    $pageTitle = $title ? $title.' | '.__('site.short_name') : $siteName;
    $pageDescription = $description ?: __('site.description');
@endphp

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ $pageDescription }}">
    @if ($noindex)
        <meta name="robots" content="noindex, nofollow">
    @endif
    <link rel="canonical" href="{{ url()->current() }}">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="ar_SA">
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:title" content="{{ $title ?? $siteName }}">
    <meta property="og:description" content="{{ $pageDescription }}">
    <meta property="og:url" content="{{ url()->current() }}">
    @if ($image)
        <meta property="og:image" content="{{ $image }}">
    @endif
    <meta name="twitter:card" content="summary_large_image">
    <meta name="theme-color" content="#2E6898">
    @include('partials.favicons')
    @fonts
    {{-- Livewire للتنقّل السريع (wire:navigate) وحده، في الرأس فيُحمَّل مرة واحدة لا مع كل صفحة. ونمطه يحمل رمز
         الطلب الأول فيضعه Livewire على سكربتات الصفحات التالية (docs/decisions.md). وصفحة تُحمَّل كاملاً بلا Livewire.
         وسكربته مؤجل فلا يوقف عرض الصفحة، وتغيّر إصداره (?id=) بعد نشر يعيد تحميل الصفحة عند التنقّل التالي
         (السمتان من AppServiceProvider لمسارات الواجهة وحدها) --}}
    @if (\App\Support\SpaLink::currentPage(request()))
        @livewireStyles
        @livewireScripts
    @endif
    {{-- صفحة لمسجّل دخول: لا تُعرض من ذاكرة الرجوع في المتصفح بعد الخروج، بل تُطلب من الخادم (page.js) --}}
    @auth
        <meta name="signed-in" content="1">
    @endauth
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{ $head ?? '' }}
</head>
{{-- data-no-progress-bar: يقرؤه Livewire عند بدئه، فشريط التقدّم من الموقع (page.js) بدل شريطه، لأن دوره role="bar" غير صالح --}}
<body class="flex min-h-screen flex-col" data-no-progress-bar>
    <a href="#main" class="sr-only z-50 rounded-lg bg-primary px-4 py-2 text-white focus:not-sr-only focus:fixed focus:start-4 focus:top-4">
        {{ __('site.skip_to_content') }}
    </a>

    {{-- بعد كل تنقّل سريع يُعلَن فيها عنوان الصفحة الجديدة لقارئ الشاشة (resources/js/page.js) --}}
    <p class="sr-only" aria-live="polite" data-page-announcer></p>

    @include('partials.header')

    {{-- بعد كل تنقّل سريع يُنقل التركيز إلى عنوانه h1، أو إليه إن لم يكن (resources/js/page.js)، فلا إطار حوله --}}
    <main id="main" class="flex-1 focus:outline-none" tabindex="-1" aria-label="{{ __('site.main_content') }}">
        {{ $slot }}
    </main>

    @include('partials.footer')

    {{-- شريط تقدّم التمرير وزر العودة للأعلى (resources/js/motion.js). والزر في جهة البداية، والمساعد في جهة النهاية --}}
    <span data-scroll-progress aria-hidden="true"></span>
    <button type="button" data-back-to-top aria-label="{{ __('site.back_to_top') }}"
        class="btn-shine fixed bottom-4 start-4 z-30 inline-flex size-12 items-center justify-center rounded-full bg-primary-dark text-white shadow-lg ring-1 ring-white/20 hover:bg-primary">
        {{ \App\Support\Icon::svg('arrow-up', 'size-5') }}
    </button>

    @include('partials.assistant')
</body>
</html>
