{{-- التخطيط العام للموقع: عربي من اليمين، ببيانات المشاركة لكل صفحة --}}
@props([
    'title' => null,
    'description' => null,
    'image' => null,
    'noindex' => false,
    // false: بلا canonical ولا og:url، لصفحة في رابطها سر (معرض الشواهد)
    'canonical' => true,
    // article لصفحة الخبر، مع وقت نشره (og:type وarticle:published_time)
    'ogType' => 'website',
    'publishedTime' => null,
])

@php
    $siteName = __('site.name');
    // الصفحة التالية من قائمة مرقّمة رُسمت في الطلب (AppServiceProvider::PAGINATED_PAGE): رقمها في العنوان، وفي canonical
    // ما لم تكن في الطلب تصفية (فالمصفّاة تشير إلى الأصل كما كانت). وصفحة بلا ترقيم لا يغيّرها ?page
    $paginated = request()->attributes->get(\App\Providers\AppServiceProvider::PAGINATED_PAGE);
    $pageNumber = is_int($paginated) && $paginated > 1 ? $paginated : null;
    $titleText = $title && $pageNumber ? $title.' - '.__('site.page_number', ['number' => $pageNumber]) : $title;
    $pageTitle = $titleText ? $titleText.' | '.__('site.short_name') : $siteName;
    $pageDescription = $description ?: __('site.description');
    $canonicalUrl = url()->current().($pageNumber && array_keys(request()->query()) === ['page'] ? '?page='.$pageNumber : '');
    $shareImage = \App\Support\ShareImage::url($image);
    $defaultShareImage = \App\Support\ShareImage::isDefault($shareImage);
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
    @stack('head')
    @if ($canonical)
        <link rel="canonical" href="{{ $canonicalUrl }}">
    @endif
    <meta property="og:type" content="{{ $ogType === 'article' ? 'article' : 'website' }}">
    @if ($ogType === 'article' && filled($publishedTime))
        <meta property="article:published_time" content="{{ $publishedTime }}">
    @endif
    <meta property="og:locale" content="ar_SA">
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:title" content="{{ $titleText ?? $siteName }}">
    <meta property="og:description" content="{{ $pageDescription }}">
    @if ($canonical)
        <meta property="og:url" content="{{ $canonicalUrl }}">
    @endif
    <meta property="og:image" content="{{ $shareImage }}">
    {{-- الصورة الافتراضية شعار شبه مربع: بأبعاده ونصه البديل، وبطاقة summary الصغيرة لا الكبيرة فلا يُقص --}}
    @if ($defaultShareImage)
        <meta property="og:image:width" content="{{ \App\Support\ShareImage::DEFAULT_WIDTH }}">
        <meta property="og:image:height" content="{{ \App\Support\ShareImage::DEFAULT_HEIGHT }}">
        <meta property="og:image:alt" content="{{ __('site.share_image_alt', ['name' => $siteName]) }}">
    @endif
    <meta name="twitter:image" content="{{ $shareImage }}">
    <meta name="twitter:card" content="{{ $defaultShareImage ? 'summary' : 'summary_large_image' }}">
    <meta name="theme-color" content="#1F4565">
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
        {{-- «لا تحفظ قراءاتي»: في الرأس غير المخزن لكل طلب، لا في صفحة المكتبة المخزنة للجميع، فلا يرسل المتصفح بلاغاً (library-progress.js) --}}
        @if (auth()->user()?->reading_tracking_disabled_at !== null)
            <meta name="reading-tracking" content="off">
        @endif
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

    {{-- شريط العودة في كل صفحة داخلية، لا الرئيسية (App\Support\BackNavigation). وفي صفحة لها رأس (x-page-hero) يرسمه الرأس
         في أعلاه كما في التصور النهائي، والمحتوى يُرسم قبل التخطيط فيُعرف ذلك هنا --}}
    @if (\App\Support\BackNavigation::shows(request()->route()) && ! str_contains((string) $slot, 'data-page-hero'))
        <x-back-nav />
    @endif

    {{-- بعد كل تنقّل سريع يُنقل التركيز إلى عنوانه h1، أو إليه إن لم يكن (resources/js/page.js)، فلا إطار حوله --}}
    <main id="main" class="flex-1 focus:outline-none" tabindex="-1" aria-label="{{ __('site.main_content') }}">
        {{ $slot }}
    </main>

    @include('partials.footer')

    {{-- شريط تقدّم التمرير (resources/js/motion.js). وزر العودة للأعلى في سطر التذييل كما في التصور النهائي --}}
    <span data-scroll-progress aria-hidden="true"></span>

    @include('partials.search-palette')
    @include('partials.page-transition')
    @include('partials.assistant')
</body>
</html>
