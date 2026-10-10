{{--
    رأس الصفحة (docs/design/ui-reference.md — الرأس): الشعار يميناً، ثم القائمة الرئيسية
    (تُدار من لوحة الإدارة)، ثم البحث، ثم زر الدخول الكبسولي في أقصى اليسار.
    العنصر النشط ذهبي بخط تحته. وعلى الجوال: الشعار يميناً وزر القائمة يساراً.
    $siteMenu يأتي من App\View\Composers\SiteChromeComposer.
--}}
@php($menu = $siteMenu ?? collect())

<header data-site-header class="sticky top-0 z-40 border-b border-line/60 bg-white/95 backdrop-blur transition-shadow data-scrolled:shadow-md">
    <div class="container-page flex h-20 items-center justify-between gap-6">
        <x-brand />

        <nav class="hidden flex-1 justify-center xl:flex" aria-label="{{ __('site.main_navigation') }}">
            <ul class="flex items-center gap-1">
                @foreach ($menu as $item)
                    @php($current = $item->isCurrent() || $item->hasCurrentChild())
                    <li class="relative">
                        @if ($item->children->isNotEmpty())
                            <button type="button" data-toggle="dropdown" aria-expanded="false" aria-controls="menu-{{ $item->id }}"
                                @class([
                                    'relative flex items-center gap-1 px-3 py-2 text-[15px] font-medium transition hover:text-gold-dark',
                                    'text-gold-dark after:absolute after:inset-x-3 after:-bottom-1 after:h-0.5 after:rounded-full after:bg-gold' => $current,
                                    'text-ink/80' => ! $current,
                                ])>
                                {{ $item->label }}
                                {{ svg('heroicon-o-chevron-down', 'size-3.5') }}
                            </button>
                            <ul id="menu-{{ $item->id }}" hidden class="absolute start-0 top-full z-50 mt-3 min-w-56 rounded-2xl border border-line/70 bg-white p-2 shadow-lg">
                                @foreach ($item->children as $child)
                                    <li>
                                        <a href="{{ $child->href() }}" @if ($child->opensInNewTab) target="_blank" rel="noopener" @endif
                                            @if ($child->isCurrent()) aria-current="page" @endif
                                            class="block rounded-xl px-4 py-2.5 text-ink hover:bg-sand hover:text-primary aria-[current=page]:text-gold-dark">{{ $child->label }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <a href="{{ $item->href() }}" @if ($item->opensInNewTab) target="_blank" rel="noopener" @endif
                                @if ($current) aria-current="page" @endif
                                @class([
                                    'relative block px-3 py-2 text-[15px] font-medium transition hover:text-gold-dark',
                                    'text-gold-dark after:absolute after:inset-x-3 after:-bottom-1 after:h-0.5 after:rounded-full after:bg-gold' => $current,
                                    'text-ink/80' => ! $current,
                                ])>
                                {{ $item->label }}
                            </a>
                        @endif
                    </li>
                @endforeach
            </ul>
        </nav>

        <div class="flex items-center gap-2">
            {{-- شارة البث: تظهر آلياً لبث جارٍ أو قادم وتختفي بعده (App\View\Composers\SiteChromeComposer) --}}
            @if ($liveStream ?? null)
                <a href="{{ route('live') }}" class="hidden items-center gap-2 rounded-full border border-red-200 bg-red-50 px-3 py-1.5 text-sm font-semibold text-red-700 hover:bg-red-100 sm:inline-flex">
                    <span @class(['size-2 rounded-full bg-red-600', 'animate-pulse' => $liveStream['live']]) aria-hidden="true"></span>
                    {{ __($liveStream['live'] ? 'livestream.header.live' : 'livestream.header.upcoming') }}
                </a>
            @endif

            @if (Route::has('search'))
                <a href="{{ route('search') }}" class="rounded-full p-2.5 text-ink/80 hover:bg-sand hover:text-primary" aria-label="{{ __('site.search') }}">
                    {{ svg('heroicon-o-magnifying-glass', 'size-5') }}
                </a>
            @else
                {{-- البحث الشامل لم يُبنَ بعد (ملاحظة المشرف ٧): الأيقونة معطلة بتلميح على الشاشات
                     الأوسع، وعلى الجوال الشعار وزر القائمة فقط كما في المرجع --}}
                <button type="button" aria-disabled="true" title="{{ __('site.search_soon') }}" class="hidden cursor-not-allowed rounded-full p-2.5 text-ink/35 sm:inline-flex">
                    {{ svg('heroicon-o-magnifying-glass', 'size-5') }}
                </button>
            @endif

            {{-- على الجوال يكفي الشعار وزر القائمة، والدخول داخل القائمة --}}
            <div class="hidden sm:block">
                @auth
                    <x-button :href="route('account.dashboard')" size="sm" class="px-5">{{ __('site.my_account') }}</x-button>
                @else
                    <x-button :href="route('login')" size="sm" class="px-5">{{ __('site.login') }}</x-button>
                @endauth
            </div>

            <button type="button" data-toggle="mobile-menu" aria-expanded="false" aria-controls="mobile-menu"
                class="rounded-full p-2.5 text-ink hover:bg-sand xl:hidden">
                <span class="sr-only">{{ __('site.open_menu') }}</span>
                {{ svg('heroicon-o-bars-3', 'size-6') }}
            </button>
        </div>
    </div>

    {{-- قائمة الجوال واللوحي --}}
    <div id="mobile-menu" hidden class="border-t border-line bg-white xl:hidden">
        <nav class="container-page max-h-[75vh] overflow-y-auto py-3" aria-label="{{ __('site.main_navigation') }}">
            <ul class="space-y-1">
                @foreach ($menu as $item)
                    <li>
                        @if ($item->children->isNotEmpty())
                            <button type="button" data-toggle="dropdown" aria-expanded="false" aria-controls="mobile-menu-{{ $item->id }}"
                                class="flex w-full items-center justify-between rounded-xl px-4 py-3 font-medium text-ink hover:bg-sand">
                                {{ $item->label }}
                                {{ svg('heroicon-o-chevron-down', 'size-4') }}
                            </button>
                            <ul id="mobile-menu-{{ $item->id }}" hidden class="ms-4 space-y-1 border-s-2 border-gold/40 ps-3">
                                @foreach ($item->children as $child)
                                    <li><a href="{{ $child->href() }}" class="block rounded-xl px-4 py-2.5 text-ink hover:bg-sand">{{ $child->label }}</a></li>
                                @endforeach
                            </ul>
                        @else
                            <a href="{{ $item->href() }}" @if ($item->isCurrent()) aria-current="page" @endif
                                class="block rounded-xl px-4 py-3 font-medium text-ink hover:bg-sand aria-[current=page]:text-gold-dark">{{ $item->label }}</a>
                        @endif
                    </li>
                @endforeach
            </ul>

            <div class="mt-3 border-t border-line pt-3 sm:hidden">
                @auth
                    <x-button :href="route('account.dashboard')" class="w-full">{{ __('site.my_account') }}</x-button>
                @else
                    <x-button :href="route('login')" class="w-full">{{ __('site.login') }}</x-button>
                @endauth
            </div>
        </nav>
    </div>
</header>
