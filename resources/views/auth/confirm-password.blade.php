<x-layouts.auth :title="__('account.confirm_password.title')" :intro="__('account.confirm_password.intro')" :image="false">
    <form method="POST" action="{{ route('password.confirm.store') }}" class="space-y-4" novalidate>
        @csrf

        <x-field name="password" type="password" :label="__('account.login.password')" :placeholder="__('account.login.password')" icon="lock-closed" hide-label
            autocomplete="current-password" required autofocus />

        <x-button class="w-full">{{ __('account.confirm_password.submit') }}</x-button>
    </form>
</x-layouts.auth>
