@php
    // رقم على بطاقتي الطلبات والبرامج: المفتوح منها والقادم
    $counts = ['account.requests.index' => $summary['open_requests'], 'account.programs.index' => $summary['upcoming_count']];
@endphp

<x-layouts.account :title="__('account.dashboard.title')" welcome>
    @unless ($user->hasVerifiedEmail())
        <x-alert type="warning" class="flex flex-wrap items-center justify-between gap-3">
            <span>{{ __('account.dashboard.verify_email') }}</span>
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <x-button variant="link">{{ __('account.dashboard.resend_email') }}</x-button>
            </form>
        </x-alert>
    @endunless

    @unless ($user->hasVerifiedPhone())
        <x-alert type="warning" class="flex flex-wrap items-center justify-between gap-3">
            <span>{{ __('account.dashboard.verify_phone') }}</span>
            <x-button variant="link" :href="route('account.phone.verify')">{{ __('account.dashboard.verify_now') }}</x-button>
        </x-alert>
    @endunless

    @include('account.partials.attention', ['summary' => $summary])

    <h2 class="sr-only">{{ __('account.dashboard.shortcuts') }}</h2>
    <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
        @foreach (\App\Support\AccountNavigation::for($user)->filter(fn (array $link) => isset($link['description'])) as $link)
            <a href="{{ route($link['route']) }}"
                class="group flex flex-col rounded-2xl border border-line/70 bg-white p-6 shadow-xs transition hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-md">
                <span class="flex items-start gap-4">
                    <span class="text-gold">{{ svg('heroicon-o-'.$link['icon'], 'size-9') }}</span>
                    <span>
                        <span class="flex items-center gap-2 text-lg font-bold text-ink">
                            {{ __($link['label']) }}
                            @if (($counts[$link['route']] ?? 0) > 0)
                                <span class="inline-flex min-w-6 items-center justify-center rounded-full bg-gold px-2 text-sm font-semibold text-white">{{ number_format($counts[$link['route']]) }}</span>
                            @endif
                        </span>
                        <span class="mt-1 block text-sm leading-6 text-muted">{{ __($link['description']) }}</span>
                    </span>
                </span>
                <span class="mt-5 inline-flex size-9 items-center justify-center self-end rounded-full bg-primary text-white transition group-hover:bg-primary-dark">
                    {{ svg('heroicon-o-arrow-left', 'size-4') }}
                </span>
            </a>
        @endforeach
    </div>

    @include('account.partials.summary', ['summary' => $summary])
</x-layouts.account>
