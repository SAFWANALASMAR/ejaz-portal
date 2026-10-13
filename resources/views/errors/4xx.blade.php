{{--
    صفحة خطأ من جهة الزائر (404 و403 و419 و429 وغيرها) بالقالب العام: رأس الموقع وتذييله، ولا تفاصيل
    تقنية، ولا فهرسة. ولارافيل يعرضها لكل رمز 4xx لا صفحة خاصة له.
--}}
@php
    $code = $exception->getStatusCode();
    $key = trans()->has('errors.'.$code.'.title') ? 'errors.'.$code : 'errors.client';
@endphp

<x-layouts.app :title="__($key.'.title')" noindex>
    <section class="py-16 sm:py-24">
        <div class="container-page flex flex-col items-center text-center">
            <p class="text-6xl font-bold text-accent sm:text-7xl" dir="ltr">{{ $code }}</p>
            <span class="mt-6 block h-1 w-12 rounded-full bg-accent" aria-hidden="true"></span>
            <h1 class="mt-4 text-2xl font-bold text-primary sm:text-3xl">{{ __($key.'.title') }}</h1>
            <p class="mt-4 max-w-xl text-lg leading-8 text-muted">{{ __($key.'.message') }}</p>

            <div class="mt-8 flex flex-wrap justify-center gap-3">
                <x-button :href="route('home')" arrow>{{ __('errors.home') }}</x-button>
                @if (Route::has('contact'))
                    <x-button variant="outline" :href="route('contact')">{{ __('errors.contact') }}</x-button>
                @endif
            </div>
        </div>
    </section>
</x-layouts.app>
