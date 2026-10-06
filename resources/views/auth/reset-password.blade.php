<x-layouts.auth :title="__('account.reset.title')">
    <form method="POST" action="{{ route('password.update') }}" class="space-y-5" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <x-field name="email" type="email" :label="__('account.register.email')" :value="$request->query('email')"
            autocomplete="email" dir="ltr" required />

        <x-field name="password" type="password" :label="__('account.profile.new_password')" :hint="__('account.register.password_hint')"
            autocomplete="new-password" dir="ltr" required autofocus />

        <x-field name="password_confirmation" type="password" :label="__('account.register.password_confirmation')"
            autocomplete="new-password" dir="ltr" required />

        <x-button class="w-full">{{ __('account.reset.submit') }}</x-button>
    </form>
</x-layouts.auth>
