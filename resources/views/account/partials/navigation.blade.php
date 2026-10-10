{{-- عناصر قائمة لوحة المستخدم، مشتركة بين الشريط الجانبي والقائمة المنسدلة --}}
<ul class="space-y-1">
    @foreach ($links as $link)
        @php($active = \App\Support\AccountNavigation::isActive($link))
        <li>
            <a href="{{ route($link['route']) }}" @if ($active) aria-current="page" @endif
                @class([
                    'flex items-center gap-3 rounded-xl px-4 py-3 font-medium transition',
                    'bg-white/15 text-white' => $active,
                    'text-white/80 hover:bg-white/10 hover:text-white' => ! $active,
                ])>
                {{ svg('heroicon-o-'.$link['icon'], 'size-5 shrink-0 '.($active ? 'text-accent' : 'text-white/70')) }}
                {{ __($link['label']) }}
            </a>
        </li>
    @endforeach

    @if ($canAdmin)
        <li>
            <a href="{{ url('/admin') }}" class="flex items-center gap-3 rounded-xl px-4 py-3 font-medium text-white/80 transition hover:bg-white/10 hover:text-white">
                {{ svg('heroicon-o-cog-6-tooth', 'size-5 shrink-0 text-white/70') }}
                {{ __('account.nav.admin') }}
            </a>
        </li>
    @endif
</ul>

<form method="POST" action="{{ route('logout') }}" class="mt-3 border-t border-white/15 pt-3">
    @csrf
    <button type="submit" class="flex w-full items-center gap-3 rounded-xl px-4 py-3 text-start font-medium text-white/80 transition hover:bg-white/10 hover:text-white">
        {{ svg('heroicon-o-arrow-right-start-on-rectangle', 'size-5 shrink-0 text-white/70') }}
        {{ __('account.nav.logout') }}
    </button>
</form>
