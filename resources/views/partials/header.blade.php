{{--
    رأس الموقع صفّان (أمر التصور النهائي، النسخة 63 — البندان R2 وR3، وFinal-Home)، وكله ثابت عند التمرير (sticky top:0)، ومنفصل
    عن البطل بتدرج أغمق وحد سفلي #8EC5F0 وظل (البند G6):
    - الصف الأول: رمز الشعار كبيراً في أقصى البداية، ثم المخطوطة كبيرة ممتدة، ثم مربع الترخيص بسطريه (من «محتوى الرئيسية»)، ثم
      البحث و«تسجيل الدخول» في أقصى النهاية. ولا سطر إشراف.
    - الصف الثاني: أفتح قليلاً وبينهما فاصل #8EC5F0، فيه عناصر القائمة كبسولات متساوية بأيقونة صغيرة بجانب الكلمة (القائمة «ج»)،
      والنشط بحد #8EC5F0 وخلفية أفتح. والقائمة تُدار من اللوحة، وعناصرها السبعة من MainMenuSevenSeeder.
    - الجوال واللوحي: الرمز والمخطوطة وزر ☰ لقائمة منسدلة بالعناصر والأيقونات نفسها.
    والقائمة لا تتسع (عناصر أكثر أو أسماء أطول): زر القائمة بدلها (app.js). $siteMenu من App\View\Composers\SiteChromeComposer،
    والأيقونة من مسار الرابط (App\Support\MenuIcon).
--}}
@php
    $menu = $siteMenu ?? collect();
    $header = fn (string $key): string => \App\Support\HomeContent::text('header', $key);
@endphp

<header data-site-header class="site-header sticky top-0 z-40 text-white">
    <div class="header-top mx-auto flex max-w-[82.5rem] items-center gap-3 px-4 py-2.5 sm:px-7 lg:gap-4 lg:py-3.5">
        <x-brand light size="masthead" class="min-w-0" />

        {{-- مربع الترخيص: بخلفية الشريط نفسها وحد رفيع، سطران يُعدَّلان من «محتوى الرئيسية» --}}
        <p data-header-licence class="header-licence hidden lg:flex">
            <span class="font-extrabold">{{ $header('licensed_by') }}</span>
            @if (filled($licenseNumber = \App\Support\MissingText::shown($header('license_number'))))
                <span class="text-brand-light">{{ $licenseNumber }}</span>
            @endif
        </p>

        <span class="flex-1" aria-hidden="true"></span>

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

    {{-- الصف الثاني: القائمة «ج» --}}
    <nav data-main-nav class="header-nav hidden nav:block" aria-label="{{ __('site.main_navigation') }}">
        <ul class="nav-capsules mx-auto max-w-[82.5rem] px-4 sm:px-7">
            @foreach ($menu as $item)
                @php($current = $item->isCurrent() || $item->hasCurrentChild())
                <li class="relative min-w-0" @if ($item->children->isNotEmpty()) data-nav-dropdown @endif>
                    @if ($item->children->isNotEmpty())
                        {{-- تفتح بمرور الفأرة بمهلة قصيرة، وبالضغط واللمس ولوحة المفاتيح (app.js — ملاحظات المدير، 1 نوفمبر، البند ٣).
                             وأولها الصفحة الأم ثم أقسامها (MenuSections، البند ٤)، والروابط تُجلب مسبقاً عند المرور (.hover) --}}
                        <button type="button" data-toggle="dropdown" aria-expanded="false" aria-controls="menu-{{ $item->id }}" aria-haspopup="true"
                            @if ($current) data-current @endif
                            class="nav-capsule">
                            <x-icon-tile :icon="\App\Support\MenuIcon::for($item)" size="xs" />
                            <span class="truncate">{{ $item->label }}</span>
                            {{ \App\Support\Icon::svg('chevron-down', 'size-3 shrink-0 opacity-80') }}
                        </button>
                        <ul id="menu-{{ $item->id }}" hidden data-dropdown-menu class="nav-dropdown absolute start-0 top-full z-50 min-w-56 rounded-2xl border border-line/70 bg-pale p-2 text-ink shadow-xl">
                            @foreach ($item->children as $child)
                                <li>
                                    <a href="{{ $child->href() }}" @if ($child->opensInNewTab) target="_blank" rel="noopener" @endif @if ($child->navigates()) wire:navigate.hover @endif
                                        @if ($loop->first ? $child->isExactlyCurrent() : $child->isCurrent()) aria-current="page" @endif
                                        @class(['block rounded-xl px-4 py-2.5 text-ink hover:bg-sky-light/60 hover:text-primary-dark focus-visible:bg-sky-light/60 aria-[current=page]:font-bold aria-[current=page]:text-primary-dark', 'mb-1 border-b border-line/60 font-extrabold' => $loop->first])>{{ $child->label }}</a>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <a href="{{ $item->href() }}" @if ($item->opensInNewTab) target="_blank" rel="noopener" @endif @if ($item->navigates()) wire:navigate.hover @endif
                            @if ($current) aria-current="page" @endif
                            class="nav-capsule">
                            <x-icon-tile :icon="\App\Support\MenuIcon::for($item)" size="xs" />
                            <span class="truncate">{{ $item->label }}</span>
                        </a>
                    @endif
                </li>
            @endforeach
        </ul>
    </nav>

    {{-- قائمة الجوال واللوحي: العناصر نفسها بأيقوناتها --}}
    <div id="mobile-menu" hidden class="header-mobile-menu nav:hidden">
        <nav class="container-page max-h-[75vh] overflow-y-auto py-3" aria-label="{{ __('site.main_navigation') }}">
            <ul class="space-y-1">
                @foreach ($menu as $item)
                    @php($current = $item->isCurrent() || $item->hasCurrentChild())
                    <li>
                        @if ($item->children->isNotEmpty())
                            <button type="button" data-toggle="dropdown" aria-expanded="false" aria-controls="mobile-menu-{{ $item->id }}"
                                @if ($current) data-current @endif
                                class="mobile-nav-link flex w-full items-center justify-between">
                                <span class="flex items-center gap-2.5"><x-icon-tile :icon="\App\Support\MenuIcon::for($item)" size="xs" />{{ $item->label }}</span>
                                {{ \App\Support\Icon::svg('chevron-down', 'size-4') }}
                            </button>
                            <ul id="mobile-menu-{{ $item->id }}" hidden class="ms-4 space-y-1 border-s-2 border-brand-light/50 ps-3">
                                @foreach ($item->children as $child)
                                    <li><a href="{{ $child->href() }}" @if ($child->opensInNewTab) target="_blank" rel="noopener" @endif @if ($child->navigates()) wire:navigate @endif
                                        @if ($loop->first ? $child->isExactlyCurrent() : $child->isCurrent()) aria-current="page" @endif
                                        class="block rounded-xl px-4 py-2.5 text-white/90 hover:bg-white/10 aria-[current=page]:font-bold aria-[current=page]:text-brand-light">{{ $child->label }}</a></li>
                                @endforeach
                            </ul>
                        @else
                            <a href="{{ $item->href() }}" @if ($item->opensInNewTab) target="_blank" rel="noopener" @endif @if ($item->navigates()) wire:navigate @endif @if ($current) aria-current="page" @endif
                                class="mobile-nav-link flex items-center gap-2.5"><x-icon-tile :icon="\App\Support\MenuIcon::for($item)" size="xs" />{{ $item->label }}</a>
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
