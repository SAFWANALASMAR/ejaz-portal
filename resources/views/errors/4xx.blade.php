{{--
    صفحة خطأ من جهة الزائر (404 و403 و419 و429 وغيرها) بالقالب العام: رأس الموقع وتذييله، ولا تفاصيل
    تقنية، ولا فهرسة. ولارافيل يعرضها لكل رمز 4xx لا صفحة خاصة له.
--}}
@php
    $code = $exception->getStatusCode();
    $key = trans()->has('errors.'.$code.'.title') ? 'errors.'.$code : 'errors.client';
@endphp

<x-layouts.app :title="__($key.'.title')" noindex>
    {{-- برأس الصفحات الداخلية في التصور النهائي (concept-hero): الصورة في جهة النهاية تحت التدرج الداكن، ورقم الخطأ كبيراً ثم
         العنوان والشريط الأزرق والرسالة والزران. والحركة تتوقف لمن طلب تقليلها --}}
    <section class="concept-hero" data-size="page" data-error-page>
        <div class="concept-hero-media">
            <img src="{{ asset('images/placeholders/architecture.svg') }}" alt="" width="1280" height="640">
        </div>
        <div class="concept-hero-scrim" aria-hidden="true"></div>
        <div class="relative mx-auto flex min-h-[26rem] max-w-[82.5rem] flex-col justify-center gap-3 px-4 py-12 sm:px-7">
            <x-icon-tile icon="exclamation-triangle" variant="glass" tone="sky" size="lg" class="rise-1" />
            <p class="error-code rise-1 text-7xl font-black sm:text-8xl" dir="ltr">{{ $code }}</p>
            <h1 class="rise-2 text-[2rem] leading-[1.15] font-black text-white sm:text-[2.75rem]">{{ __($key.'.title') }}</h1>
            <span class="title-bar" aria-hidden="true"></span>
            <p class="rise-3 max-w-xl text-lg leading-8 text-white/90">{{ __($key.'.message') }}</p>

            <div class="mt-4 flex flex-wrap gap-3">
                <a href="{{ route('home') }}" wire:navigate class="hero-cta w-auto! px-7">
                    {{ __('errors.home') }}
                    {{ \App\Support\Icon::svg('arrow-left', 'size-4 shrink-0', ['aria-hidden' => 'true']) }}
                </a>
                @if (Route::has('contact'))
                    <a href="{{ route('contact') }}" wire:navigate class="hero-pill px-7! py-3! text-lg!">{{ __('errors.contact') }}</a>
                @endif
            </div>
        </div>
    </section>
</x-layouts.app>
