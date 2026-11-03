@php
    // رقم على بطاقتي الطلبات والبرامج: المفتوح منها والقادم
    $counts = ['account.requests.index' => $summary['open_requests'], 'account.programs.index' => $summary['upcoming_count']];
@endphp

<x-layouts.account :title="__('account.dashboard.title')" welcome>
    <x-slot:belowWelcome>{{ $library }}</x-slot:belowWelcome>

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

    {{-- البطاقات ملونة (Final-Dashboard ‎.cc — البند D1): داكنة ثم فاتحة بالتعاقب (.tone-card)، بأيقونتها وعددها وسهمها. وعمودان ثابتان من
         اللوحي فأعلى، فلا تبقى بطاقة وحدها في وسط صف (قرار صفوان على طلب الدمج 142، 3 نوفمبر 2026) --}}
    <h2 class="sr-only">{{ __('account.dashboard.shortcuts') }}</h2>
    <ul class="grid gap-4 sm:grid-cols-2" data-reveal-group data-account-cards>
        {{-- البطاقات من اللوحة (AccountCard): ومنها لوحة الإدارة لمن يدخلها، تحميلاً كاملاً (SpaLink) --}}
        @foreach (\App\Support\AccountNavigation::cards($user) as $card)
            <li class="flex">
                <a href="{{ $card['href'] }}" @if ($card['external']) target="_blank" rel="noopener noreferrer" @else @navigate($card['href']) @endif
                    class="tone-card group flex w-full flex-col gap-2 p-5.5">
                    <span class="flex items-center justify-between gap-3">
                        <x-icon-tile :icon="$card['icon']" size="md" />
                        @if (($counts[$card['route']] ?? 0) > 0)
                            <span class="text-[1.625rem] font-black tabular-nums">{{ number_format($counts[$card['route']]) }}</span>
                        @endif
                    </span>
                    <span class="text-[1.3125rem] font-black">{{ $card['title'] }}</span>
                    @if (filled($card['description']))
                        <span class="tone-card-muted text-[15px] leading-6">{{ $card['description'] }}</span>
                    @endif
                    <span class="mt-auto inline-flex size-[2.375rem] items-center justify-center self-end rounded-full border border-current/40 bg-white/15 transition group-hover:bg-white/25" aria-hidden="true">
                        {{ \App\Support\Icon::svg($card['external'] ? 'arrow-up-left' : 'arrow-left', 'size-4') }}
                    </span>
                    @if ($card['external'])
                        <span class="sr-only">({{ __('site.opens_in_new_tab') }})</span>
                    @endif
                </a>
            </li>
        @endforeach
    </ul>

    @include('account.partials.stats', ['stats' => $stats])

    {{-- «قراءاتي ومسموعاتي» (Final-Dashboard — docs/decisions/2026-11-01-my-reading.md): أحدث ما بدأه في المكتبة ومجاميعه --}}
    @include('account.partials.my-reading', ['reading' => $reading, 'trackingDisabled' => $user->reading_tracking_disabled_at !== null])

    {{-- «برامجي» من برامج المكتبة بتقدّمها، و«برامج أتممتها» (docs/decisions/2026-11-02-reading-program.md) --}}
    @include('account.partials.library-programs', ['registrations' => $libraryPrograms, 'completed' => $completedPrograms, 'progress' => $programProgress])

    @include('account.partials.summary', ['summary' => $summary])
</x-layouts.account>
