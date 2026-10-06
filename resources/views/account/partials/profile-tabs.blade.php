{{-- تبويبات «بياناتي»: البيانات الأساسية، والأمان، والخصوصية — كبسولية والنشط أخضر --}}
@php
    $tabs = [
        ['route' => 'account.profile', 'label' => 'account.profile.tab'],
        ['route' => 'account.security', 'label' => 'account.security.title'],
        ['route' => 'account.data', 'label' => 'account.data.tab'],
    ];
@endphp

<nav aria-label="{{ __('account.nav.profile') }}" class="-mx-1 overflow-x-auto px-1">
    <ul class="flex gap-2">
        @foreach ($tabs as $tab)
            @php($active = request()->routeIs($tab['route']))
            <li class="shrink-0">
                <a href="{{ route($tab['route']) }}" @if ($active) aria-current="page" @endif
                    @class([
                        'block rounded-full px-5 py-2 text-sm font-semibold transition',
                        'bg-primary text-white' => $active,
                        'border border-line bg-white text-ink hover:border-primary' => ! $active,
                    ])>{{ __($tab['label']) }}</a>
            </li>
        @endforeach
    </ul>
</nav>
