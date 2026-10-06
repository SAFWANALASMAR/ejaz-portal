{{-- الرمز من التطبيق افتراضياً، ورمز الاسترداد عند فقد الجوال (?recovery=1) --}}
@php($recovery = request()->boolean('recovery') || $errors->has('recovery_code'))

<x-layouts.auth :title="__('account.two_factor_challenge.title')"
    :intro="$recovery ? __('account.two_factor_challenge.recovery_intro') : __('account.two_factor_challenge.intro')">
    <form method="POST" action="{{ route('two-factor.login.store') }}" class="space-y-5" novalidate>
        @csrf

        @if ($recovery)
            <x-field name="recovery_code" :label="__('validation.attributes.recovery_code')" autocomplete="one-time-code" dir="ltr" required autofocus />
        @else
            <x-field name="code" :label="__('account.two_factor_challenge.code')" inputmode="numeric" autocomplete="one-time-code"
                maxlength="6" dir="ltr" required autofocus />
        @endif

        <x-button class="w-full">{{ __('account.two_factor_challenge.submit') }}</x-button>
    </form>

    <x-slot:footer>
        @if ($recovery)
            <a href="{{ route('two-factor.login') }}" class="text-primary hover:underline">{{ __('account.two_factor_challenge.use_code') }}</a>
        @else
            <a href="{{ route('two-factor.login', ['recovery' => 1]) }}" class="text-primary hover:underline">{{ __('account.two_factor_challenge.use_recovery') }}</a>
        @endif
    </x-slot:footer>
</x-layouts.auth>
