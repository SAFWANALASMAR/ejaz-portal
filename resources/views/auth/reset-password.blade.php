<x-layouts.auth :title="__('account.reset.title')">
    <form method="POST" action="{{ route('password.update') }}" class="space-y-4" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <x-field name="email" type="email" :label="__('account.register.email')" :placeholder="__('account.register.email')" icon="envelope" hide-label
            :value="$request->query('email')" autocomplete="email" dir="auto" required />

        <x-field name="password" type="password" :label="__('account.profile.new_password')" :placeholder="__('account.profile.new_password')" icon="lock-closed" hide-label
            :hint="__('account.register.password_hint')" autocomplete="new-password" required autofocus />

        <x-field name="password_confirmation" type="password" :label="__('account.register.password_confirmation')" :placeholder="__('account.register.password_confirmation')"
            icon="lock-closed" hide-label autocomplete="new-password" required />

        <x-button class="w-full">{{ __('account.reset.submit') }}</x-button>
    </form>
</x-layouts.auth>
