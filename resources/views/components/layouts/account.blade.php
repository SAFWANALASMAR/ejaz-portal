{{-- لوحة المستخدم: قائمة جانبية ثابتة على سطح المكتب، وأفقية قابلة للتمرير على الجوال --}}
@props(['title'])

@php
    $user = auth()->user();
    $links = collect(config('account.navigation'))
        ->filter(fn (array $link) => ! isset($link['can']) || $user->can($link['can']))
        ->filter(fn (array $link) => Route::has($link['route']));
@endphp

<x-layouts.app :title="$title" noindex>
    <div class="bg-sand/60">
        <div class="container-page grid gap-6 py-8 lg:grid-cols-[16rem_1fr] lg:py-12">
            <aside>
                <nav aria-label="{{ __('site.my_account') }}" class="lg:sticky lg:top-24">
                    <ul class="flex gap-2 overflow-x-auto pb-2 lg:flex-col lg:overflow-visible lg:pb-0">
                        @foreach ($links as $link)
                            @php($active = request()->routeIs($link['route'].'*'))
                            <li class="shrink-0">
                                <a href="{{ route($link['route']) }}" @if ($active) aria-current="page" @endif
                                    class="block rounded-lg px-4 py-2.5 font-medium whitespace-nowrap {{ $active ? 'bg-primary text-white' : 'bg-white text-ink hover:bg-primary-soft' }}">
                                    {{ __($link['label']) }}
                                </a>
                            </li>
                        @endforeach

                        @if ($user->canAccessPanel(filament()->getPanel('admin')))
                            <li class="shrink-0">
                                <a href="{{ url('/admin') }}" class="block rounded-lg bg-white px-4 py-2.5 font-medium whitespace-nowrap text-gold-dark hover:bg-primary-soft">
                                    {{ __('account.nav.admin') }}
                                </a>
                            </li>
                        @endif

                        <li class="shrink-0">
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="block w-full rounded-lg bg-white px-4 py-2.5 text-start font-medium whitespace-nowrap text-red-800 hover:bg-red-50">
                                    {{ __('account.nav.logout') }}
                                </button>
                            </form>
                        </li>
                    </ul>
                </nav>
            </aside>

            <section class="min-w-0 space-y-6">
                <h1 class="text-2xl font-bold text-primary sm:text-3xl">{{ $title }}</h1>
                <x-flash />
                {{ $slot }}
            </section>
        </div>
    </div>
</x-layouts.app>
