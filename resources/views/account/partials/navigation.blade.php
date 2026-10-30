{{-- عناصر قائمة لوحة المستخدم، مشتركة بين الشريط الجانبي والقائمة المنسدلة --}}
<ul class="space-y-1">
    @foreach ($links as $link)
        @php($active = \App\Support\AccountNavigation::isActive($link))
        <li>
            <a href="{{ route($link['route']) }}" @navigate(route($link['route'])) @if ($active) aria-current="page" @endif
                @class([
                    'flex items-center gap-3 rounded-xl px-4 py-3 font-medium transition',
                    'bg-primary-dark/60 text-white' => $active,
                    'text-white/90 hover:bg-primary-dark/40 hover:text-white' => ! $active,
                ])>
                {{-- الأيقونة في مربع مستدير كبطاقات الرئيسية (التجميل — 25 أكتوبر 2026) --}}
                <span @class(['inline-flex size-8 shrink-0 items-center justify-center rounded-lg', 'bg-white text-primary-dark' => $active, 'bg-white/10 text-white/80' => ! $active]) aria-hidden="true">
                    {{ \App\Support\Icon::svg($link['icon'], 'size-5') }}
                </span>
                {{ __($link['label']) }}
            </a>
        </li>
    @endforeach

    @if ($canAdmin)
        <li>
            <a href="{{ url('/admin') }}" class="flex items-center gap-3 rounded-xl px-4 py-3 font-medium text-white/90 transition hover:bg-primary-dark/40 hover:text-white">
                <span class="inline-flex size-8 shrink-0 items-center justify-center rounded-lg bg-white/10 text-white/80" aria-hidden="true">
                    {{ \App\Support\Icon::svg('cog-6-tooth', 'size-5') }}
                </span>
                {{ __('account.nav.admin') }}
            </a>
        </li>
    @endif
</ul>

<form method="POST" action="{{ route('logout') }}" class="mt-3 border-t border-white/15 pt-3">
    @csrf
    <button type="submit" class="flex w-full items-center gap-3 rounded-xl px-4 py-3 text-start font-medium text-white/90 transition hover:bg-primary-dark/40 hover:text-white">
        <span class="inline-flex size-8 shrink-0 items-center justify-center rounded-lg bg-white/10 text-white/80" aria-hidden="true">
            {{ \App\Support\Icon::svg('arrow-right-start-on-rectangle', 'size-5') }}
        </span>
        {{ __('account.nav.logout') }}
    </button>
</form>
