<x-layouts.auth :title="__('account.forgot.title')" :intro="__('account.forgot.intro')">
    <form method="POST" action="{{ route('password.email') }}" class="space-y-4" novalidate>
        @csrf

        <x-field name="email" type="email" :label="__('account.register.email')" :placeholder="__('account.register.email')" icon="envelope" hide-label
            autocomplete="email" dir="auto" required autofocus />

        <x-button class="w-full">{{ __('account.forgot.submit') }}</x-button>
    </form>

    <x-or-divider class="my-5" />

    <x-button variant="outline" :href="route('login')" class="w-full">{{ __('account.forgot.back_to_login') }}</x-button>
</x-layouts.auth>
