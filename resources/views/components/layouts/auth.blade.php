{{--
    صفحات الدخول والتسجيل والاستعادة (docs/design/ui-reference.md — صفحة الدخول):
    بطاقة في المنتصف فوق خلفية الهوية، بعنوان ووصف، وصورة معمارية في أسفلها.
--}}
@props(['title', 'heading' => null, 'intro' => null, 'image' => true])

<x-layouts.app :title="$title" noindex>
    <div class="relative overflow-hidden bg-sand py-10 sm:py-16">
        <img src="{{ asset('images/decor/pattern-corner.svg') }}" alt="" aria-hidden="true"
            class="pointer-events-none absolute -top-10 -start-10 w-64 opacity-60">
        <img src="{{ asset('images/decor/pattern-corner.svg') }}" alt="" aria-hidden="true"
            class="pointer-events-none absolute -top-10 -end-10 w-64 opacity-60">

        <div class="relative mx-auto w-full max-w-md px-4">
            <div class="overflow-hidden rounded-3xl border border-line/70 bg-white shadow-[0_20px_50px_-25px_rgba(31,79,75,0.35)]">
                <div class="p-6 sm:p-8">
                    <h1 class="text-2xl font-bold text-primary sm:text-3xl">{{ $heading ?? $title }}</h1>
                    @if ($intro)
                        <p class="mt-2 leading-7 text-muted">{{ $intro }}</p>
                    @endif

                    <x-flash class="mt-5" />

                    <div class="mt-6">
                        {{ $slot }}
                    </div>
                </div>

                @if ($image)
                    <img src="{{ asset('images/placeholders/architecture.svg') }}" alt="" aria-hidden="true"
                        class="h-36 w-full object-cover sm:h-40">
                @endif
            </div>
        </div>
    </div>
</x-layouts.app>
