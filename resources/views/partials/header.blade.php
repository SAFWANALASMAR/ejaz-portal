{{--
    رأس الصفحة: الشعار، والقائمة الرئيسية (تُدار من لوحة الإدارة)، والبحث، والحساب.
    $siteMenu يأتي من App\View\Composers\SiteMenuComposer.
--}}
@php($menu = $siteMenu ?? collect())

<header class="sticky top-0 z-40 border-b border-line bg-white/95 backdrop-blur">
    <div class="container-page flex h-18 items-center justify-between gap-4">
        @include('partials.brand')

        <nav class="hidden lg:block" aria-label="{{ __('site.main_navigation') }}">
            <ul class="flex items-center gap-1">
                @foreach ($menu as $item)
                    <li class="relative">
                        @if ($item->children->isNotEmpty())
                            <button type="button" data-toggle="dropdown" aria-expanded="false" aria-controls="menu-{{ $item->id }}"
                                class="flex items-center gap-1 rounded-lg px-3 py-2 font-medium text-ink hover:bg-sand hover:text-primary aria-expanded:bg-sand">
                                {{ $item->label }}
                                <svg class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
                            </button>
                            <ul id="menu-{{ $item->id }}" hidden class="absolute start-0 top-full mt-2 min-w-56 rounded-xl border border-line bg-white p-2 shadow-lg">
                                @foreach ($item->children as $child)
                                    <li>
                                        <a href="{{ $child->href() }}" @if ($child->opens_in_new_tab) target="_blank" rel="noopener" @endif
                                            class="block rounded-lg px-3 py-2 text-ink hover:bg-sand hover:text-primary">{{ $child->label }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <a href="{{ $item->href() }}" @if ($item->opens_in_new_tab) target="_blank" rel="noopener" @endif
                                @if ($item->isCurrent()) aria-current="page" @endif
                                class="block rounded-lg px-3 py-2 font-medium text-ink hover:bg-sand hover:text-primary aria-[current=page]:text-primary aria-[current=page]:underline aria-[current=page]:decoration-gold aria-[current=page]:underline-offset-8">
                                {{ $item->label }}
                            </a>
                        @endif
                    </li>
                @endforeach
            </ul>
        </nav>

        <div class="flex items-center gap-2">
            @if (Route::has('search'))
                <a href="{{ route('search') }}" class="rounded-lg p-2 text-ink hover:bg-sand hover:text-primary" aria-label="{{ __('site.search') }}">
                    <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.2-5.2M10.5 18a7.5 7.5 0 100-15 7.5 7.5 0 000 15z"/></svg>
                </a>
            @endif

            @auth
                <x-button :href="route('account.dashboard')" size="sm" class="hidden sm:inline-flex">{{ __('site.my_account') }}</x-button>
            @else
                <x-button :href="route('login')" size="sm" class="hidden sm:inline-flex">{{ __('site.login') }}</x-button>
            @endauth

            <button type="button" data-toggle="mobile-menu" aria-expanded="false" aria-controls="mobile-menu"
                class="rounded-lg p-2 text-ink hover:bg-sand lg:hidden">
                <span class="sr-only">{{ __('site.open_menu') }}</span>
                <svg class="size-7" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/></svg>
            </button>
        </div>
    </div>

    {{-- قائمة الجوال --}}
    <div id="mobile-menu" hidden class="border-t border-line bg-white lg:hidden">
        <nav class="container-page max-h-[75vh] overflow-y-auto py-3" aria-label="{{ __('site.main_navigation') }}">
            <ul class="space-y-1">
                @foreach ($menu as $item)
                    <li>
                        @if ($item->children->isNotEmpty())
                            <button type="button" data-toggle="dropdown" aria-expanded="false" aria-controls="mobile-menu-{{ $item->id }}"
                                class="flex w-full items-center justify-between rounded-lg px-3 py-2.5 font-medium text-ink hover:bg-sand">
                                {{ $item->label }}
                                <svg class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
                            </button>
                            <ul id="mobile-menu-{{ $item->id }}" hidden class="ms-4 space-y-1 border-s border-line ps-3">
                                @foreach ($item->children as $child)
                                    <li><a href="{{ $child->href() }}" class="block rounded-lg px-3 py-2 text-ink hover:bg-sand">{{ $child->label }}</a></li>
                                @endforeach
                            </ul>
                        @else
                            <a href="{{ $item->href() }}" class="block rounded-lg px-3 py-2.5 font-medium text-ink hover:bg-sand">{{ $item->label }}</a>
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
