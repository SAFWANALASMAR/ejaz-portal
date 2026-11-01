{{-- عناصر قائمة لوحة المستخدم، مشتركة بين الشريط الجانبي والقائمة المنسدلة --}}
<ul class="space-y-1">
    @foreach ($links as $link)
        @php($active = \App\Support\AccountNavigation::isActive($link))
        <li>
            <a href="{{ route($link['route']) }}" @navigate(route($link['route'])) @if ($active) aria-current="page" @endif
                @class([
                    'flex items-center gap-3 rounded-xl px-4 py-3 font-medium transition',
                    'group bg-white/15 text-white ring-1 ring-white/20' => $active,
                    'group text-white/90 hover:bg-white/10 hover:text-white' => ! $active,
                ])>
                {{-- الأيقونة في مربع مستدير كبطاقات الرئيسية (التجميل — 25 أكتوبر 2026) --}}
                <x-icon-tile :icon="$link['icon']" variant="gradient" size="sm" :class="$active ? 'nav-tile nav-tile-active' : 'nav-tile'" />
                {{ __($link['label']) }}
            </a>
        </li>
    @endforeach

    @if ($canAdmin)
        <li>
            <a href="{{ url('/admin') }}" class="flex items-center gap-3 rounded-xl px-4 py-3 font-medium text-white/90 transition hover:bg-white/10 hover:text-white">
                <x-icon-tile icon="cog-6-tooth" variant="gradient" size="sm" class="nav-tile" />
                {{ __('account.nav.admin') }}
            </a>
        </li>
    @endif
</ul>

<form method="POST" action="{{ route('logout') }}" class="mt-3 border-t border-white/15 pt-3">
    @csrf
    <button type="submit" class="flex w-full items-center gap-3 rounded-xl px-4 py-3 text-start font-medium text-white/90 transition hover:bg-white/10 hover:text-white">
        <x-icon-tile icon="arrow-right-start-on-rectangle" variant="gradient" size="sm" class="nav-tile" />
        {{ __('account.nav.logout') }}
    </button>
</form>
