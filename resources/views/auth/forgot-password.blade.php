<x-layouts.auth :title="__('account.forgot.title')" :intro="__('account.forgot.intro')">
    <form method="POST" action="{{ route('password.email') }}" class="space-y-5" novalidate>
        @csrf

        <x-field name="email" type="email" :label="__('account.register.email')" autocomplete="email" dir="ltr" required autofocus />

        <x-button class="w-full">{{ __('account.forgot.submit') }}</x-button>
    </form>

    <x-slot:footer>
        <a href="{{ route('login') }}" class="font-semibold text-primary hover:underline">{{ __('account.forgot.back_to_login') }}</a>
    </x-slot:footer>
</x-layouts.auth>
