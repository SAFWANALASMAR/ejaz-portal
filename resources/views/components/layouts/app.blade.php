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
    $pageDescription = $description ?: __('site.tagline');
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
    <meta name="theme-color" content="#1F4F4B">
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{ $head ?? '' }}
</head>
<body class="flex min-h-screen flex-col">
    <a href="#main" class="sr-only z-50 rounded-lg bg-primary px-4 py-2 text-white focus:not-sr-only focus:fixed focus:start-4 focus:top-4">
        {{ __('site.skip_to_content') }}
    </a>

    @include('partials.header')

    <main id="main" class="flex-1" tabindex="-1">
        {{ $slot }}
    </main>

    @include('partials.footer')
</body>
</html>
