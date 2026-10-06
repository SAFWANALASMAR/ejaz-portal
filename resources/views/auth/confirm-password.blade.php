<x-layouts.auth :title="__('account.confirm_password.title')" :intro="__('account.confirm_password.intro')">
    <form method="POST" action="{{ route('password.confirm.store') }}" class="space-y-5" novalidate>
        @csrf

        <x-field name="password" type="password" :label="__('account.login.password')" autocomplete="current-password" dir="ltr" required autofocus />

        <x-button class="w-full">{{ __('account.confirm_password.submit') }}</x-button>
    </form>
</x-layouts.auth>
