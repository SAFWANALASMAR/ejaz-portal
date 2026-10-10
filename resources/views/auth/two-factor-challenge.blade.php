{{-- الرمز من التطبيق افتراضياً، ورمز الاسترداد عند فقد الجوال (?recovery=1) --}}
@php($recovery = request()->boolean('recovery') || $errors->has('recovery_code'))

<x-layouts.auth :title="__('account.two_factor_challenge.title')" :image="false"
    :intro="$recovery ? __('account.two_factor_challenge.recovery_intro') : __('account.two_factor_challenge.intro')">
    <form method="POST" action="{{ route('two-factor.login.store') }}" class="space-y-4" novalidate>
        @csrf

        @if ($recovery)
            <x-field name="recovery_code" :label="__('validation.attributes.recovery_code')" :placeholder="__('validation.attributes.recovery_code')" icon="key" hide-label
                autocomplete="one-time-code" dir="ltr" required autofocus />
        @else
            <x-field name="code" :label="__('account.two_factor_challenge.code')" :placeholder="__('account.two_factor_challenge.code')" icon="device-phone-mobile" hide-label
                inputmode="numeric" autocomplete="one-time-code" maxlength="6" dir="ltr" required autofocus />
        @endif

        <x-button class="w-full">{{ __('account.two_factor_challenge.submit') }}</x-button>
    </form>

    <x-or-divider class="my-5" />

    @if ($recovery)
        <x-button variant="outline" :href="route('two-factor.login')" class="w-full">{{ __('account.two_factor_challenge.use_code') }}</x-button>
    @else
        <x-button variant="outline" :href="route('two-factor.login', ['recovery' => 1])" class="w-full">{{ __('account.two_factor_challenge.use_recovery') }}</x-button>
    @endif
</x-layouts.auth>
