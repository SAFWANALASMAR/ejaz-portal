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
    <div class="mesh-light pb-14">
        @if ($welcome)
            <div class="container-page pt-8">
                {{-- شريط الترحيب بلغة البطل: صورة معمارية تحت تدرج الهوية ونقش هندسي، ومربع أيقونة زجاجي --}}
                <div class="section-dark relative overflow-hidden rounded-3xl">
                    <img src="{{ asset('images/placeholders/architecture.svg') }}" alt="" aria-hidden="true" width="1280" height="208" class="absolute inset-0 -z-10 size-full object-cover">
                    <div class="page-hero-scrim absolute inset-0 -z-10" aria-hidden="true"></div>
                    <div class="bg-pattern-light absolute inset-0 -z-10" aria-hidden="true"></div>
                    <div class="flex min-h-44 items-center gap-4 px-6 py-8 sm:min-h-52 sm:px-10">
                        <x-icon-tile icon="user" variant="glass" tone="sky" size="lg" />
                        <div>
                            <h1 class="text-2xl font-black text-white sm:text-3xl">{{ __('account.dashboard.welcome', ['name' => $user->name]) }}</h1>
                            <p class="mt-1 text-white/85">{{ __('account.dashboard.intro') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <div class="container-page grid gap-6 pt-6 lg:grid-cols-[17rem_1fr]">
            <aside>
                {{-- الجوال واللوحي: قائمة منسدلة --}}
                <details class="account-nav group rounded-2xl text-white lg:hidden">
                    <summary class="flex cursor-pointer list-none items-center justify-between px-5 py-3.5 font-semibold">
                        <span class="flex items-center gap-3">
                            @if ($current)
                                {{ \App\Support\Icon::svg($current['icon'], 'size-5 text-accent-light') }}
                                {{ __($current['label']) }}
                            @else
                                {{ __('site.my_account') }}
                            @endif
                        </span>
                        {{ \App\Support\Icon::svg('chevron-down', 'size-5 transition group-open:rotate-180') }}
                    </summary>
                    <nav class="border-t border-white/10 p-2" aria-label="{{ __('site.my_account') }}">
                        @include('account.partials.navigation', ['links' => $links, 'canAdmin' => $canAdmin])
                    </nav>
                </details>

                {{-- الحاسوب: شريط جانبي ثابت --}}
                <nav aria-label="{{ __('site.my_account') }}" class="account-nav hidden rounded-3xl p-3 text-white shadow-lg lg:sticky lg:top-24 lg:block">
                    @include('account.partials.navigation', ['links' => $links, 'canAdmin' => $canAdmin])
                </nav>
            </aside>

            <section class="min-w-0 space-y-6">
                @unless ($welcome)
                    <div>
                        <h1 class="text-2xl font-black text-primary-dark sm:text-3xl">{{ $title }}</h1>
                        <svg class="flourish mt-2" viewBox="0 0 120 14" fill="none" aria-hidden="true">
                            <path d="M2 9c18-7 36-7 54 0s38 7 62-2" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" />
                        </svg>
                    </div>
                @endunless

                <x-flash />

                {{ $slot }}
            </section>
        </div>
    </div>
</x-layouts.app>
