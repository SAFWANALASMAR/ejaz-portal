{{--
    رأس الصفحة (التصور النهائي الملزم — docs/design/final-concept، وقرارا صفوان: الرأس داكن والشعار والمخطوطة بالأبيض والمخطوطة
    أكبر): أزرق داكن صلب بلون التذييل. في جهة البداية الشعار الرسمي والمخطوطة وتحتهما سطر الإشراف، ثم القائمة الرئيسية في
    المنتصف (تُدار من اللوحة، وتلتف صفين إن لم تتسع)، والنشط بخلفية أفتح وحد بالأزرق الفاتح، ثم زر الدخول الكبسولي الأزرق
    وزر البحث السريع (Ctrl+K). وتحت عرض nav (app.css) زر القائمة بدل القائمة.
    $siteMenu يأتي من App\View\Composers\SiteChromeComposer.

    كل عنصر في الحاسوب مربع صغير وأيقونته المتدرجة فوق اسمه (الخيار «ب» — صفوان، 31 أكتوبر 01:38)، وفي قائمة الجوال بجانبه، والأيقونة من مسار رابطه (App\Support\MenuIcon).
--}}
@php($menu = $siteMenu ?? collect())

<header data-site-header class="site-header sticky top-0 z-40 text-white">
    <div class="mx-auto flex max-w-[82.5rem] items-center justify-between gap-4 px-4 py-3 sm:px-7 nav:gap-7">
        <a href="{{ route('home') }}" wire:navigate class="flex shrink-0 flex-col gap-1">
            <x-brand light :link="false" />
            <span class="hidden text-xs text-white/70 sm:block">{{ \App\Support\HomeContent::text('footer', 'supervision') }}</span>
        </a>

        <nav data-main-nav class="hidden min-w-0 flex-1 justify-center nav:flex" aria-label="{{ __('site.main_navigation') }}">
            <ul class="flex flex-wrap items-stretch justify-center gap-1.5">
                @foreach ($menu as $item)
                    @php($current = $item->isCurrent() || $item->hasCurrentChild())
                    <li class="relative shrink-0">
                        @if ($item->children->isNotEmpty())
                            <button type="button" data-toggle="dropdown" aria-expanded="false" aria-controls="menu-{{ $item->id }}"
                                @if ($current) data-current @endif
                                class="nav-link relative flex h-full shrink-0 items-center whitespace-nowrap text-[13px]">
                                <x-icon-tile :icon="\App\Support\MenuIcon::for($item)" size="xs" :index="$loop->index" />
                                <span class="flex items-center gap-0.5">{{ $item->label }}{{ \App\Support\Icon::svg('chevron-down', 'size-3') }}</span>
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
                                class="nav-link relative flex h-full shrink-0 items-center whitespace-nowrap text-[13px]">
                                <x-icon-tile :icon="\App\Support\MenuIcon::for($item)" size="xs" :index="$loop->index" />
                                {{ $item->label }}
                            </a>
                        @endif
                    </li>
                @endforeach
            </ul>
        </nav>

        <div class="flex shrink-0 items-center gap-2.5">
            {{-- شارة البث: تظهر آلياً لبث جارٍ أو قادم وتختفي بعده (App\View\Composers\SiteChromeComposer) --}}
            @if ($liveStream ?? null)
                <a href="{{ route('live') }}" wire:navigate class="hidden shrink-0 items-center gap-2 whitespace-nowrap rounded-full bg-white px-3 py-1.5 text-sm font-semibold text-red-700 hover:bg-red-50 sm:inline-flex">
                    <span @class(['size-2 rounded-full bg-red-600', 'animate-pulse' => $liveStream['live']]) aria-hidden="true"></span>
                    {{ __($liveStream['live'] ? 'livestream.header.live' : 'livestream.header.upcoming') }}
                </a>
            @endif

            {{-- على الجوال يكفي الشعار وزر القائمة، والدخول داخل القائمة --}}
            <div class="hidden sm:block">
                @auth
                    <a href="{{ route('account.dashboard') }}" @navigate(route('account.dashboard')) class="login-pill">
                        <span data-icon-circle class="inline-flex rounded-full">{{ \App\Support\Icon::svg('user', 'size-[18px]', ['aria-hidden' => 'true']) }}</span>
                        {{ __('site.my_account') }}
                    </a>
                @else
                    <a href="{{ route('login') }}" class="login-pill">
                        <span data-icon-circle class="inline-flex rounded-full">{{ \App\Support\Icon::svg('user', 'size-[18px]', ['aria-hidden' => 'true']) }}</span>
                        {{ __('site.login') }}
                    </a>
                @endauth
            </div>

            {{-- البحث السريع (partials/search-palette): نافذة بـ Ctrl+K أو بالضغط --}}
            <button type="button" data-search-open
                class="header-icon-button" aria-label="{{ __('ui.search.open') }}" aria-keyshortcuts="Control+K Meta+K" title="{{ __('ui.search.open') }} ({{ __('ui.search.shortcut') }})">
                {{ \App\Support\Icon::svg('magnifying-glass', 'size-[19px]', ['aria-hidden' => 'true']) }}
            </button>

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
                                <span class="flex items-center gap-2.5"><x-icon-tile :icon="\App\Support\MenuIcon::for($item)" size="xs" :index="$loop->index" />{{ $item->label }}</span>
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
                                class="flex items-center gap-2.5 rounded-xl px-4 py-3 font-medium text-white hover:bg-white/10 aria-[current=page]:font-bold aria-[current=page]:text-brand-light"><x-icon-tile :icon="\App\Support\MenuIcon::for($item)" size="xs" :index="$loop->index" />{{ $item->label }}</a>
                        @endif
                    </li>
                @endforeach
            </ul>

            <div class="mt-3 border-t border-white/10 pt-3 sm:hidden">
                @auth
                    <a href="{{ route('account.dashboard') }}" @navigate(route('account.dashboard')) class="login-pill w-full justify-center">
                        {{ \App\Support\Icon::svg('user', 'size-[18px]', ['aria-hidden' => 'true']) }}
                        {{ __('site.my_account') }}
                    </a>
                @else
                    <a href="{{ route('login') }}" class="login-pill w-full justify-center">
                        {{ \App\Support\Icon::svg('user', 'size-[18px]', ['aria-hidden' => 'true']) }}
                        {{ __('site.login') }}
                    </a>
                @endauth
            </div>
        </nav>
    </div>
</header>
