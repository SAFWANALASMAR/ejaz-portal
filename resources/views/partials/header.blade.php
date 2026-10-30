{{--
    رأس الصفحة الداكن (التصور المعتمد، صفوان 30 أكتوبر 11:58، ومرجع المدير التنفيذي 17:42 — plans/2026-10-30-visual-refresh-2-directive.md
    وplans/2026-10-30-director-home-reference.md): أزرق داكن صلب بلون التذييل (primary-dark)، والشعار في دائرة والمخطوطة
    بالأبيض أكبر، ثم القائمة الرئيسية (تُدار من اللوحة) بنص أبيض والنشط بخط تحته بلون التمييز على الداكن، ثم زر البحث
    السريع (Ctrl+K)، ثم زر الدخول كبسولي أبيض بأيقونة في دائرة. وعلى الجوال: الشعار يميناً وزر القائمة يساراً.
    القائمة صف واحد لا تنكسر عباراته: تظهر من العرض nav (app.css)، وبين nav و1440 بكسل خطها وحشوها أصغر قليلاً، وإن لم
    تتسع ظهر زر القائمة بدلها (resources/js/app.js).
    $siteMenu يأتي من App\View\Composers\SiteChromeComposer.
--}}
@php($menu = $siteMenu ?? collect())

<header data-site-header class="site-header sticky top-0 z-40 text-white">
    {{-- أعرض من محتوى الصفحة، لتتسع القائمة بجانب الشعار الكامل ومخطوطته (ملاحظات المدير التنفيذي، 25 أكتوبر 2026) --}}
    <div class="container-page flex h-20 max-w-[88rem] items-center justify-between gap-3 lg:px-6 nav:max-[90rem]:gap-2">
        <x-brand light round />

        <nav data-main-nav class="hidden min-w-0 flex-1 justify-center nav:flex" aria-label="{{ __('site.main_navigation') }}">
            <ul class="flex items-center">
                @foreach ($menu as $item)
                    @php($current = $item->isCurrent() || $item->hasCurrentChild())
                    <li class="relative shrink-0">
                        @if ($item->children->isNotEmpty())
                            <button type="button" data-toggle="dropdown" aria-expanded="false" aria-controls="menu-{{ $item->id }}"
                                @if ($current) data-current @endif
                                class="nav-link relative flex shrink-0 items-center gap-1 whitespace-nowrap px-[0.3rem] py-2 text-[14.5px] font-medium nav:max-[90rem]:px-1 nav:max-[90rem]:text-[13.5px]">
                                {{ $item->label }}
                                {{ \App\Support\Icon::svg('chevron-down', 'size-3.5') }}
                            </button>
                            <ul id="menu-{{ $item->id }}" hidden class="absolute start-0 top-full z-50 mt-3 min-w-56 rounded-2xl border border-line/70 bg-white p-2 text-ink shadow-xl">
                                @foreach ($item->children as $child)
                                    <li>
                                        <a href="{{ $child->href() }}" @if ($child->opensInNewTab) target="_blank" rel="noopener" @endif @if ($child->navigates()) wire:navigate @endif
                                            @if ($child->isCurrent()) aria-current="page" @endif
                                            class="block rounded-xl px-4 py-2.5 text-ink hover:bg-surface hover:text-primary aria-[current=page]:font-bold aria-[current=page]:text-accent">{{ $child->label }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <a href="{{ $item->href() }}" @if ($item->opensInNewTab) target="_blank" rel="noopener" @endif @if ($item->navigates()) wire:navigate @endif
                                @if ($current) aria-current="page" @endif
                                class="nav-link relative block shrink-0 whitespace-nowrap px-[0.3rem] py-2 text-[14.5px] font-medium nav:max-[90rem]:px-1 nav:max-[90rem]:text-[13.5px]">
                                {{ $item->label }}
                            </a>
                        @endif
                    </li>
                @endforeach
            </ul>
        </nav>

        <div class="flex shrink-0 items-center gap-2">
            {{-- شارة البث: تظهر آلياً لبث جارٍ أو قادم وتختفي بعده (App\View\Composers\SiteChromeComposer) --}}
            @if ($liveStream ?? null)
                <a href="{{ route('live') }}" wire:navigate class="hidden shrink-0 items-center gap-2 whitespace-nowrap rounded-full bg-white px-3 py-1.5 text-sm font-semibold text-red-700 hover:bg-red-50 sm:inline-flex">
                    <span @class(['size-2 rounded-full bg-red-600', 'animate-pulse' => $liveStream['live']]) aria-hidden="true"></span>
                    {{ __($liveStream['live'] ? 'livestream.header.live' : 'livestream.header.upcoming') }}
                </a>
            @endif

            {{-- البحث السريع (partials/search-palette): نافذة بـ Ctrl+K أو بالضغط. وبلا سكربت رابط إلى بحث الحوكمة --}}
            <a href="{{ Route::has('governance.index') ? route('governance.index') : route('home') }}" data-search-open
                class="header-icon-button" aria-label="{{ __('ui.search.open') }}" aria-keyshortcuts="Control+K Meta+K" title="{{ __('ui.search.open') }} ({{ __('ui.search.shortcut') }})">
                {{ \App\Support\Icon::svg('magnifying-glass', 'size-5', ['aria-hidden' => 'true']) }}
            </a>

            {{-- على الجوال يكفي الشعار وزر القائمة، والدخول داخل القائمة --}}
            <div class="hidden sm:block">
                @auth
                    <a href="{{ route('account.dashboard') }}" @navigate(route('account.dashboard')) class="login-pill">
                        <span data-icon-circle class="login-pill-icon">{{ \App\Support\Icon::svg('user', 'size-4', ['aria-hidden' => 'true']) }}</span>
                        {{ __('site.my_account') }}
                    </a>
                @else
                    <a href="{{ route('login') }}" class="login-pill">
                        <span data-icon-circle class="login-pill-icon">{{ \App\Support\Icon::svg('arrow-right-end-on-rectangle', 'size-4', ['aria-hidden' => 'true']) }}</span>
                        {{ __('site.login') }}
                    </a>
                @endauth
            </div>

            <button type="button" data-toggle="mobile-menu" aria-expanded="false" aria-controls="mobile-menu"
                class="header-icon-button nav:hidden">
                <span class="sr-only">{{ __('site.open_menu') }}</span>
                {{ \App\Support\Icon::svg('bars-3', 'size-6', ['aria-hidden' => 'true']) }}
            </button>
        </div>
    </div>

    {{-- قائمة الجوال واللوحي --}}
    <div id="mobile-menu" hidden class="border-t border-white/10 bg-primary-dark nav:hidden">
        <nav class="container-page max-h-[75vh] overflow-y-auto py-3" aria-label="{{ __('site.main_navigation') }}">
            <ul class="space-y-1">
                @foreach ($menu as $item)
                    <li>
                        @if ($item->children->isNotEmpty())
                            <button type="button" data-toggle="dropdown" aria-expanded="false" aria-controls="mobile-menu-{{ $item->id }}"
                                class="flex w-full items-center justify-between rounded-xl px-4 py-3 font-medium text-white hover:bg-white/10">
                                {{ $item->label }}
                                {{ \App\Support\Icon::svg('chevron-down', 'size-4') }}
                            </button>
                            <ul id="mobile-menu-{{ $item->id }}" hidden class="ms-4 space-y-1 border-s-2 border-brand-light/50 ps-3">
                                @foreach ($item->children as $child)
                                    <li><a href="{{ $child->href() }}" @if ($child->opensInNewTab) target="_blank" rel="noopener" @endif @if ($child->navigates()) wire:navigate @endif
                                        @if ($child->isCurrent()) aria-current="page" @endif
                                        class="block rounded-xl px-4 py-2.5 text-white/90 hover:bg-white/10 aria-[current=page]:font-bold aria-[current=page]:text-brand-light">{{ $child->label }}</a></li>
                                @endforeach
                            </ul>
                        @else
                            <a href="{{ $item->href() }}" @if ($item->opensInNewTab) target="_blank" rel="noopener" @endif @if ($item->navigates()) wire:navigate @endif @if ($item->isCurrent()) aria-current="page" @endif
                                class="block rounded-xl px-4 py-3 font-medium text-white hover:bg-white/10 aria-[current=page]:font-bold aria-[current=page]:text-brand-light">{{ $item->label }}</a>
                        @endif
                    </li>
                @endforeach
            </ul>

            <div class="mt-3 border-t border-white/10 pt-3 sm:hidden">
                @auth
                    <a href="{{ route('account.dashboard') }}" @navigate(route('account.dashboard')) class="login-pill w-full justify-center">
                        <span class="login-pill-icon">{{ \App\Support\Icon::svg('user', 'size-4', ['aria-hidden' => 'true']) }}</span>
                        {{ __('site.my_account') }}
                    </a>
                @else
                    <a href="{{ route('login') }}" class="login-pill w-full justify-center">
                        <span class="login-pill-icon">{{ \App\Support\Icon::svg('arrow-right-end-on-rectangle', 'size-4', ['aria-hidden' => 'true']) }}</span>
                        {{ __('site.login') }}
                    </a>
                @endauth
            </div>
        </nav>
    </div>
</header>
