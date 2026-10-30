{{--
    صفحة خطأ من جهة الزائر (404 و403 و419 و429 وغيرها) بالقالب العام: رأس الموقع وتذييله، ولا تفاصيل
    تقنية، ولا فهرسة. ولارافيل يعرضها لكل رمز 4xx لا صفحة خاصة له.
--}}
@php
    $code = $exception->getStatusCode();
    $key = trans()->has('errors.'.$code.'.title') ? 'errors.'.$code : 'errors.client';
@endphp

<x-layouts.app :title="__($key.'.title')" noindex>
    {{-- بلغة البطل (الدفعة الثانية): رقم الخطأ كبيراً بنص متدرج فوق خلفية الهوية الداكنة ونقش هندسي يدور ببطء، ثم العنوان
         والرسالة والزران. والحركة تتوقف لمن طلب تقليلها --}}
    <section class="section-dark py-16 sm:py-24">
        <div class="page-hero-scrim absolute inset-0 -z-10" aria-hidden="true"></div>
        <div class="bg-pattern-light absolute inset-0 -z-10" aria-hidden="true"></div>
        <div class="hero-geometry absolute -z-10" data-loop aria-hidden="true"></div>
        <div class="container-page flex flex-col items-center text-center">
            <x-icon-tile icon="exclamation-triangle" variant="glass" tone="sky" size="xl" class="float-soft" data-loop />
            <p class="error-code mt-6 text-7xl font-black sm:text-8xl" dir="ltr">{{ $code }}</p>
            <h1 class="mt-4 text-2xl font-black text-white sm:text-3xl">{{ __($key.'.title') }}</h1>
            <p class="mt-4 max-w-xl text-lg leading-8 text-white/85">{{ __($key.'.message') }}</p>

            <div class="mt-8 flex flex-wrap justify-center gap-3">
                <a href="{{ route('home') }}" wire:navigate class="btn-shine inline-flex items-center gap-2 rounded-full bg-brand-light px-7 py-3 text-lg font-bold text-primary-deep transition hover:bg-white">
                    {{ __('errors.home') }}
                    {{ \App\Support\Icon::svg('arrow-left', 'size-4 shrink-0', ['aria-hidden' => 'true']) }}
                </a>
                @if (Route::has('contact'))
                    <a href="{{ route('contact') }}" wire:navigate class="inline-flex items-center rounded-full px-7 py-3 text-lg font-semibold text-white ring-1 ring-white/50 transition hover:bg-white/10">{{ __('errors.contact') }}</a>
                @endif
            </div>
        </div>
    </section>
</x-layouts.app>
