<x-layouts.account :title="__('account.phone.title')">
    <x-card class="max-w-xl">
        @if ($user->phone)
            <p class="leading-7 text-ink">
                {{ __('account.phone.intro', ['phone' => \App\Support\SaudiPhone::masked($user->phone)]) }}
            </p>

            <form method="POST" action="{{ route('account.phone.verify.store') }}" class="mt-5 space-y-5" novalidate>
                @csrf
                <x-field name="code" :label="__('account.phone.code')" inputmode="numeric" autocomplete="one-time-code"
                    maxlength="6" dir="ltr" required autofocus />
                <x-button>{{ __('account.phone.submit') }}</x-button>
            </form>

            <form method="POST" action="{{ route('account.phone.send') }}" class="mt-4">
                @csrf
                <x-button variant="link">{{ __('account.phone.resend') }}</x-button>
            </form>
        @else
            <p class="text-ink">{{ __('account.phone.no_phone') }}</p>
            <x-button :href="route('account.profile')" class="mt-4">{{ __('account.nav.profile') }}</x-button>
        @endif
    </x-card>
</x-layouts.account>
