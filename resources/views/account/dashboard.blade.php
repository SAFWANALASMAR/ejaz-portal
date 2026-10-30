@php
    // رقم على بطاقتي الطلبات والبرامج: المفتوح منها والقادم
    $counts = ['account.requests.index' => $summary['open_requests'], 'account.programs.index' => $summary['upcoming_count']];
@endphp

<x-layouts.account :title="__('account.dashboard.title')" welcome>
    {{-- تنبيها التوثيق متى فُعّل من اللوحة وحده (AccountSettings — ملاحظات المدير التنفيذي، 25 أكتوبر 2026) --}}
    @if (\App\Support\AccountSettings::verificationEnabled())
        @if ($user->email !== null && ! $user->hasVerifiedEmail())
            <x-alert type="warning" class="flex flex-wrap items-center justify-between gap-3">
                <span>{{ __('account.dashboard.verify_email') }}</span>
                <form method="POST" action="{{ route('verification.send') }}">
                    @csrf
                    <x-button variant="link">{{ __('account.dashboard.resend_email') }}</x-button>
                </form>
            </x-alert>
        @endif

        @if ($user->phone !== null && ! $user->hasVerifiedPhone())
            <x-alert type="warning" class="flex flex-wrap items-center justify-between gap-3">
                <span>{{ __('account.dashboard.verify_phone') }}</span>
                <x-button variant="link" :href="route('account.phone.verify')">{{ __('account.dashboard.verify_now') }}</x-button>
            </x-alert>
        @endif
    @endif

    @include('account.partials.attention', ['summary' => $summary])

    @include('account.partials.oversight', ['oversight' => $oversight])

    <h2 class="sr-only">{{ __('account.dashboard.shortcuts') }}</h2>
    <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3" data-reveal-group>
        {{-- البطاقات من اللوحة (AccountCard): ومنها لوحة الإدارة لمن يدخلها، تحميلاً كاملاً (SpaLink) --}}
        @foreach (\App\Support\AccountNavigation::cards($user) as $card)
            <a href="{{ $card['href'] }}" @if ($card['external']) target="_blank" rel="noopener noreferrer" @else @navigate($card['href']) @endif
                class="card-glow tilt group flex flex-col p-6">
                <span class="flex items-start gap-4">
                    <x-icon-tile :icon="$card['icon']" :tone="['blue', 'violet', 'green', 'navy', 'sky'][$loop->index % 5]" size="md" />
                    <span>
                        <span class="flex items-center gap-2 text-lg font-bold text-primary-dark">
                            {{ $card['title'] }}
                            @if (($counts[$card['route']] ?? 0) > 0)
                                <span class="inline-flex min-w-6 items-center justify-center rounded-full bg-accent px-2 text-sm font-semibold text-white">{{ number_format($counts[$card['route']]) }}</span>
                            @endif
                        </span>
                        @if (filled($card['description']))
                            <span class="mt-1 block text-sm leading-6 text-muted">{{ $card['description'] }}</span>
                        @endif
                    </span>
                </span>
                <span class="mt-5 inline-flex size-9 items-center justify-center self-end rounded-full bg-primary text-white transition group-hover:bg-primary-dark" aria-hidden="true">
                    {{ \App\Support\Icon::svg($card['external'] ? 'arrow-up-left' : 'arrow-left', 'size-4') }}
                </span>
                @if ($card['external'])
                    <span class="sr-only">({{ __('site.opens_in_new_tab') }})</span>
                @endif
            </a>
        @endforeach
    </div>

    @include('account.partials.stats', ['stats' => $stats])

    @include('account.partials.summary', ['summary' => $summary])
</x-layouts.account>
