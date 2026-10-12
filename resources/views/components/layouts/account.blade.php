{{--
    لوحة المستخدم (docs/design/ui-reference.md — لوحة المستخدم):
    شريط ترحيب بصورة معمارية في الرئيسية، وشريط جانبي أيمن أخضر داكن بأيقونات،
    و«تسجيل الخروج» منفصل في أسفله. وعلى الجوال تصير القائمة منسدلة.
--}}
@props(['title', 'welcome' => false])

@php
    $user = auth()->user();
    $links = \App\Support\AccountNavigation::for($user);
    $current = $links->first(fn (array $link) => \App\Support\AccountNavigation::isActive($link));
    $canAdmin = $user->canAccessPanel(filament()->getPanel('admin'));
@endphp

<x-layouts.app :title="$title" noindex>
    <div class="bg-surface/60 pb-14">
        @if ($welcome)
            <div class="container-page pt-8">
                <div class="relative overflow-hidden rounded-3xl border border-line/60">
                    <img src="{{ asset('images/placeholders/architecture.svg') }}" alt="" aria-hidden="true" class="h-44 w-full object-cover sm:h-52">
                    <div class="absolute inset-0 bg-linear-to-l from-white via-white/80 to-white/0"></div>
                    <div class="absolute inset-y-0 start-0 flex items-center px-6 sm:px-10">
                        <div class="flex items-start gap-3">
                            <span class="mt-1 text-primary">{{ svg('heroicon-o-user', 'size-8') }}</span>
                            <div>
                                <h1 class="text-2xl font-bold text-primary sm:text-3xl">{{ __('account.dashboard.welcome', ['name' => $user->name]) }}</h1>
                                <p class="mt-1 text-muted">{{ __('account.dashboard.intro') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <div class="container-page grid gap-6 pt-6 lg:grid-cols-[17rem_1fr]">
            <aside aria-label="{{ __('site.my_account') }}">
                {{-- الجوال واللوحي: قائمة منسدلة --}}
                <details class="group rounded-2xl bg-primary text-white lg:hidden">
                    <summary class="flex cursor-pointer list-none items-center justify-between px-5 py-3.5 font-semibold">
                        <span class="flex items-center gap-3">
                            @if ($current)
                                {{ svg('heroicon-o-'.$current['icon'], 'size-5 text-accent-light') }}
                                {{ __($current['label']) }}
                            @else
                                {{ __('site.my_account') }}
                            @endif
                        </span>
                        {{ svg('heroicon-o-chevron-down', 'size-5 transition group-open:rotate-180') }}
                    </summary>
                    <nav class="border-t border-white/10 p-2" aria-label="{{ __('site.my_account') }}">
                        @include('account.partials.navigation', ['links' => $links, 'canAdmin' => $canAdmin])
                    </nav>
                </details>

                {{-- الحاسوب: شريط جانبي ثابت --}}
                <nav aria-label="{{ __('site.my_account') }}" class="hidden rounded-3xl bg-primary p-3 text-white shadow-sm lg:sticky lg:top-24 lg:block">
                    @include('account.partials.navigation', ['links' => $links, 'canAdmin' => $canAdmin])
                </nav>
            </aside>

            <section class="min-w-0 space-y-6">
                @unless ($welcome)
                    <h1 class="text-2xl font-bold text-primary sm:text-3xl">{{ $title }}</h1>
                @endunless

                <x-flash />

                {{ $slot }}
            </section>
        </div>
    </div>
</x-layouts.app>
