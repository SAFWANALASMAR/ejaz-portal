{{--
    إدارة التحقق الثنائي. الصفحة نفسها محمية بتأكيد كلمة المرور،
    فتنجح عمليات Fortify (التفعيل والتأكيد والإيقاف) دون طلب كلمة المرور مرة أخرى.
--}}
@php
    // ورسالة كل حالة من رسائل الجلسة في القالب (components/flash)
    $status = session('status');
    $pending = $user->two_factor_secret && ! $user->two_factor_confirmed_at;
    $enabled = $user->hasEnabledTwoFactorAuthentication();
    $showCodes = in_array($status, ['two-factor-authentication-confirmed', 'recovery-codes-generated'], true) || request()->boolean('codes');
@endphp

<x-layouts.account :title="__('account.profile.title')">
    @include('account.partials.profile-tabs')

    <x-card>
        <h2 class="text-lg font-semibold text-ink">{{ __('account.security.two_factor') }}</h2>
        <p class="mt-2 leading-7 text-muted">{{ __('account.security.two_factor_intro') }}</p>

        @if ($user->requiresTwoFactor())
            <x-alert type="warning" class="mt-4">{{ __('account.security.required_for_role') }}</x-alert>
        @endif

        @if ($enabled)
            <p class="mt-4 font-semibold text-primary-dark">{{ __('account.security.enabled') }}</p>

            @if ($showCodes)
                <div class="mt-5 rounded-xl border border-line bg-surface p-5">
                    <h3 class="font-semibold text-ink">{{ __('account.security.recovery_codes') }}</h3>
                    <p class="mt-1 text-sm text-muted">{{ __('account.security.recovery_intro') }}</p>
                    <ul class="mt-4 grid grid-cols-1 gap-2 font-mono text-sm sm:grid-cols-2" dir="ltr">
                        @foreach ($user->recoveryCodes() as $code)
                            <li class="rounded bg-white px-3 py-2">{{ $code }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="mt-5 flex flex-wrap gap-3">
                @unless ($showCodes)
                    <x-button variant="secondary" :href="route('account.security', ['codes' => 1])">{{ __('account.security.show_codes') }}</x-button>
                @endunless

                <form method="POST" action="{{ route('two-factor.regenerate-recovery-codes') }}">
                    @csrf
                    <x-button variant="secondary">{{ __('account.security.regenerate') }}</x-button>
                </form>

                @if ($user->requiresTwoFactor())
                    <p class="self-center text-sm text-muted">{{ __('account.security.cannot_disable') }}</p>
                @else
                    <form method="POST" action="{{ route('two-factor.disable') }}">
                        @csrf
                        @method('DELETE')
                        <x-button variant="danger">{{ __('account.security.disable') }}</x-button>
                    </form>
                @endif
            </div>
        @elseif ($pending)
            <p class="mt-4 text-ink">{{ __('account.security.scan') }}</p>

            <div class="mt-4 inline-block rounded-xl border border-line bg-white p-4">
                {!! $user->twoFactorQrCodeSvg() !!}
            </div>

            <p class="mt-3 text-sm text-muted">{{ __('account.security.manual_key') }}</p>
            <p class="mt-1 font-mono text-sm tracking-wider" dir="ltr">{{ decrypt($user->two_factor_secret) }}</p>

            <form method="POST" action="{{ route('two-factor.confirm') }}" class="mt-5 max-w-xs space-y-4" novalidate>
                @csrf
                <x-field name="code" :label="__('account.security.confirm_code')" bag="confirmTwoFactorAuthentication"
                    inputmode="numeric" autocomplete="one-time-code" maxlength="6" dir="ltr" required autofocus />
                <x-button>{{ __('account.security.confirm') }}</x-button>
            </form>
        @else
            <p class="mt-4 text-ink">{{ __('account.security.disabled') }}</p>

            <form method="POST" action="{{ route('two-factor.enable') }}" class="mt-4">
                @csrf
                <x-button>{{ __('account.security.enable') }}</x-button>
            </form>
        @endif
    </x-card>

    <x-card class="mt-6">
        <h2 class="text-lg font-semibold text-ink">{{ __('account.security.other_sessions.title') }}</h2>
        <p class="mt-2 leading-7 text-muted">{{ __('account.security.other_sessions.intro') }}</p>

        <form method="POST" action="{{ route('account.security.other-sessions') }}" class="mt-5 max-w-sm space-y-4" novalidate>
            @csrf
            <x-field name="current_password" type="password" :label="__('account.security.other_sessions.password')" bag="logoutOtherSessions"
                autocomplete="current-password" required />
            <x-button variant="secondary">{{ __('account.security.other_sessions.submit') }}</x-button>
        </form>
    </x-card>
</x-layouts.account>
