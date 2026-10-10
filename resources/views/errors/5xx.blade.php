{{--
    صفحة خطأ الخادم (500 و503 وغيرهما) بقالب مستقل: لا رأس ولا تذييل، لأنهما يقرآن القائمة والإعدادات
    من القاعدة، والخطأ قد يكون تعطلها. فتظهر ولو تعطلت، ولا تفاصيل تقنية فيها، ولا فهرسة.
--}}
@php
    $code = $exception->getStatusCode();
    $key = trans()->has('errors.'.$code.'.title') ? 'errors.'.$code : 'errors.server';
@endphp

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __($key.'.title') }} | {{ __('site.short_name') }}</title>
    <meta name="theme-color" content="#1F4F4B">
    @fonts
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-screen flex-col">
    <header class="border-b border-line/70 bg-white">
        <div class="container-page flex h-20 items-center">
            <x-brand />
        </div>
    </header>

    <main id="main" class="flex flex-1 items-center py-16 sm:py-24">
        <div class="container-page flex flex-col items-center text-center">
            <p class="text-6xl font-bold text-gold sm:text-7xl" dir="ltr">{{ $code }}</p>
            <span class="mt-6 block h-1 w-12 rounded-full bg-gold" aria-hidden="true"></span>
            <h1 class="mt-4 text-2xl font-bold text-primary sm:text-3xl">{{ __($key.'.title') }}</h1>
            <p class="mt-4 max-w-xl text-lg leading-8 text-muted">{{ __($key.'.message') }}</p>

            <div class="mt-8">
                <x-button :href="route('home')" arrow>{{ __('errors.home') }}</x-button>
            </div>
        </div>
    </main>

    <footer class="bg-primary-dark py-5 text-center text-sm text-white/70">
        {{ __('site.name') }}
    </footer>
</body>
</html>
