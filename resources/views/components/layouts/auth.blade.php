{{-- صفحات الدخول والتسجيل: بطاقة في وسط الصفحة على خلفية مزخرفة --}}
@props(['title', 'intro' => null])

<x-layouts.app :title="$title" noindex>
    <div class="bg-sand bg-pattern py-10 sm:py-16">
        <div class="mx-auto w-full max-w-md px-4">
            <x-card class="p-6 sm:p-8">
                <h1 class="text-2xl font-bold text-primary">{{ $title }}</h1>
                @if ($intro)
                    <p class="mt-2 leading-7 text-muted">{{ $intro }}</p>
                @endif

                <x-flash class="mt-5" />

                <div class="mt-6">
                    {{ $slot }}
                </div>
            </x-card>

            @isset($footer)
                <div class="mt-6 text-center text-sm text-muted">{{ $footer }}</div>
            @endisset
        </div>
    </div>
</x-layouts.app>
