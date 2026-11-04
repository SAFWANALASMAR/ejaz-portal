{{--
    لوحة المستخدم (التصور النهائي — Final-Dashboard): شريط ترحيب بلغة البطل (الصورة في جهة النهاية تحت تدرج داكن، ودائرة
    المستخدم، و«مرحباً {الاسم}»)، وشريط جانبي أيمن أزرق داكن بأيقونات و«تسجيل الخروج» منفصل في أسفله، وعلى الجوال قائمة
    منسدلة. وفي غير الرئيسية العنوان بشريط أزرق تحته.
--}}
@props(['title', 'welcome' => false, 'description' => null])

@php
    $user = auth()->user();
    $links = \App\Support\AccountNavigation::for($user);
    $current = $links->first(fn (array $link) => \App\Support\AccountNavigation::isActive($link));
    $headIcon = $current['icon'] ?? null;
    $headDescription = $description ?? (isset($current['description']) ? __($current['description']) : null);
    $canAdmin = $user->canAccessPanel(filament()->getPanel('admin'));
@endphp

<x-layouts.app :title="$title" noindex>
    {{-- data-account: نطاق أنماط حساب المستفيد (resources/css/account-polish.css) --}}
    <div class="pb-14" data-account>
        @if ($welcome)
            {{-- الترحيب «فاتح بصورة واحدة» (التصور المعتمد للبطل والرؤوس، الدفعة ٤): تدرج سماوي ونقش باهت، والتحية باسم المستخدم في جهة
                 البداية، وصورة واحدة مائلة بإطار أبيض في جهة النهاية، وتحت النص على الجوال --}}
            <div class="mx-auto max-w-[82.5rem] px-4 pt-7 sm:px-7">
                <div class="acct-welcome" data-account-welcome>
                    <div class="acct-welcome-inner">
                        <div class="flex items-center gap-4 sm:gap-5">
                            <x-icon-tile icon="user" shade="dark" size="lg" class="shrink-0" />
                            <div class="min-w-0">
                                <span class="acct-welcome-badge">{{ __('site.my_account') }}</span>
                                <h1 class="acct-welcome-title rise-1 mt-2 text-2xl font-black sm:text-[2.25rem]">{{ __('account.dashboard.welcome', ['name' => $user->name]) }}</h1>
                                <p class="acct-welcome-intro rise-2 mt-1 text-lg">{{ __('account.dashboard.intro') }}</p>
                            </div>
                        </div>
                        <div class="acct-welcome-photo" aria-hidden="true">
                            <img src="{{ asset('images/placeholders/architecture.svg') }}" alt="" width="512" height="320">
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

            <section class="min-w-0 space-y-6" data-account-main>
                {{-- رأس الصفحة الداخلية (دفعة ٤ من طلب لوحة الإدارة، 4 نوفمبر 2026): أيقونة القسم ووصفه من config/account.php --}}
                @unless ($welcome)
                    <header class="account-head" data-account-head>
                        @if ($headIcon)
                            <x-icon-tile :icon="$headIcon" shade="dark" size="lg" class="account-head-tile" />
                        @endif
                        <div class="min-w-0">
                            <h1 class="text-2xl font-black text-primary-dark sm:text-[2rem]">{{ $title }}</h1>
                            @if ($headDescription)
                                <p class="account-head-text">{{ $headDescription }}</p>
                            @endif
                            <span class="title-bar mt-3" aria-hidden="true"></span>
                        </div>
                    </header>
                @endunless

                <x-flash />

                {{ $slot }}
            </section>
        </div>
    </div>
</x-layouts.app>
