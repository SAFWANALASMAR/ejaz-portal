{{--
    لوحة المستخدم (التصور النهائي — Final-Dashboard): شريط ترحيب بلغة البطل (الصورة في جهة النهاية تحت تدرج داكن، ودائرة
    المستخدم، و«مرحباً {الاسم}»)، وشريط جانبي أيمن أزرق داكن بأيقونات و«تسجيل الخروج» منفصل في أسفله، وعلى الجوال قائمة
    منسدلة. وفي غير الرئيسية العنوان بشريط أزرق تحته.
--}}
@props(['title', 'welcome' => false])

@php
    $user = auth()->user();
    $links = \App\Support\AccountNavigation::for($user);
    $current = $links->first(fn (array $link) => \App\Support\AccountNavigation::isActive($link));
    $canAdmin = $user->canAccessPanel(filament()->getPanel('admin'));
@endphp

<x-layouts.app :title="$title" noindex>
    <div class="pb-14">
        @if ($welcome)
            <div class="mx-auto max-w-[82.5rem] px-4 pt-7 sm:px-7">
                <div class="concept-hero rounded-[1.25rem]" data-size="page">
                    <div class="concept-hero-media">
                        <img src="{{ asset('images/placeholders/architecture.svg') }}" alt="" aria-hidden="true" width="1280" height="208">
                    </div>
                    <div class="concept-hero-scrim" aria-hidden="true"></div>
                    <div class="relative flex min-h-[10.625rem] items-center gap-5 px-6 py-8 sm:px-8">
                        <span class="inline-flex size-[4.25rem] shrink-0 items-center justify-center rounded-full border border-white/25 bg-white/10" aria-hidden="true">
                            {{ \App\Support\Icon::svg('user', 'size-8') }}
                        </span>
                        <div>
                            <h1 class="rise-1 text-2xl font-black sm:text-[2.25rem]">{{ __('account.dashboard.welcome', ['name' => $user->name]) }}</h1>
                            <p class="rise-2 mt-1 text-lg text-white/90">{{ __('account.dashboard.intro') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- ما تحت الترحيب بعرض المحتوى كله (شريط المكتبة في لوحة المستفيد — Final-Dashboard) --}}
        {{ $belowWelcome ?? '' }}

        <div class="mx-auto grid max-w-[82.5rem] gap-6 px-4 pt-6 sm:px-7 lg:grid-cols-[17.5rem_1fr]">
            <aside>
                {{-- الجوال واللوحي: قائمة منسدلة --}}
                <details class="account-nav group rounded-2xl text-white lg:hidden">
                    <summary class="flex cursor-pointer list-none items-center justify-between px-5 py-3.5 font-semibold">
                        <span class="flex items-center gap-3">
                            @if ($current)
                                {{ \App\Support\Icon::svg($current['icon'], 'size-5 text-brand-light') }}
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
                <nav aria-label="{{ __('site.my_account') }}" class="account-nav hidden rounded-[1.25rem] p-4 text-white lg:sticky lg:top-28 lg:block">
                    @include('account.partials.navigation', ['links' => $links, 'canAdmin' => $canAdmin])
                </nav>
            </aside>

            <section class="min-w-0 space-y-6">
                @unless ($welcome)
                    <div class="flex flex-col gap-2">
                        <h1 class="text-2xl font-black text-primary-dark sm:text-[2rem]">{{ $title }}</h1>
                        <span class="title-bar" aria-hidden="true"></span>
                    </div>
                @endunless

                <x-flash />

                {{ $slot }}
            </section>
        </div>
    </div>
</x-layouts.app>
