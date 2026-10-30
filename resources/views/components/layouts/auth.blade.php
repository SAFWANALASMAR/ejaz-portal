{{--
    صفحات الدخول والتسجيل والاستعادة والتحقق (ui-reference.md — صفحة الدخول، والدفعة الثانية): بطاقة بيضاء في المنتصف فوق
    خلفية الهوية الداكنة بتدرج شعاعي ونقش هندسي يدور ببطء، في رأسها مربع أيقونة زجاجي وشارة اسم الجمعية، ثم العنوان والوصف
    والنموذج، وصورة معمارية في أسفلها. والحركة تتوقف لمن طلب تقليلها.
--}}
@props(['title', 'heading' => null, 'intro' => null, 'image' => true, 'noindex' => true, 'icon' => 'lock-closed'])

<x-layouts.app :title="$title" :noindex="$noindex">
    <div class="auth-stage section-dark relative py-10 sm:py-16">
        <div class="page-hero-scrim absolute inset-0 -z-10" aria-hidden="true"></div>
        <div class="bg-pattern-light absolute inset-0 -z-10" aria-hidden="true"></div>
        <div class="hero-geometry absolute -z-10" data-loop aria-hidden="true"></div>

        <div class="relative mx-auto w-full max-w-md px-4">
            <div class="overflow-hidden rounded-[2rem] bg-white text-ink shadow-[0_30px_70px_-30px_rgba(10,30,50,0.65)] ring-1 ring-white/20">
                <div class="relative overflow-hidden bg-linear-to-bl from-primary-dark to-primary px-6 pt-6 pb-8 text-white sm:px-8">
                    <div class="bg-pattern-light absolute inset-0" aria-hidden="true"></div>
                    <div class="relative flex items-center justify-between gap-3">
                        <span class="glass inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs">{{ __('site.short_name') }}</span>
                        <x-icon-tile :icon="$icon" variant="glass" tone="sky" size="md" />
                    </div>
                    <h1 class="relative mt-5 text-2xl font-black sm:text-3xl">{{ $heading ?? $title }}</h1>
                    @if ($intro)
                        <p class="relative mt-2 leading-7 text-white/90">{{ $intro }}</p>
                    @endif
                </div>

                <div class="p-6 sm:p-8">
                    <x-flash />

                    <div class="mt-2">
                        {{ $slot }}
                    </div>
                </div>

                @if ($image)
                    <img src="{{ asset('images/placeholders/architecture.svg') }}" alt="" aria-hidden="true" width="448" height="160"
                        class="h-28 w-full object-cover opacity-80 sm:h-32">
                @endif
            </div>
        </div>
    </div>
</x-layouts.app>
