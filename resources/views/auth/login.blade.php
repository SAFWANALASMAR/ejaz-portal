<x-layouts.auth :title="__('account.login.title')" :heading="__('account.login.heading')" :intro="__('account.login.intro')">
    <form method="POST" action="{{ route('login.store') }}" class="space-y-4" novalidate>
        @csrf

        <x-field name="login" :label="__('account.login.identifier')" :placeholder="__('account.login.identifier')" icon="envelope" hide-label
            autocomplete="username" dir="auto" required autofocus />

        <x-field name="password" type="password" :label="__('account.login.password')" :placeholder="__('account.login.password')" icon="lock-closed" hide-label
            autocomplete="current-password" required />

        <div class="flex items-center justify-between gap-4 text-sm">
            <a href="{{ route('password.request') }}" class="font-medium text-primary hover:underline">{{ __('account.login.forgot') }}</a>
            <label class="flex items-center gap-2 text-muted">
                <input type="checkbox" name="remember" value="1" class="size-4 rounded border-line text-primary focus:ring-primary">
                {{ __('account.login.remember') }}
            </label>
        </div>

        <x-bot-challenge action="login" />

        <x-button class="w-full">{{ __('account.login.submit') }}</x-button>
    </form>

    <x-or-divider class="my-5" />

    <p class="mb-3 text-center text-sm text-muted">{{ __('account.login.no_account') }}</p>
    <x-button variant="outline" :href="route('register')" class="w-full">{{ __('account.login.create_account') }}</x-button>
</x-layouts.auth>
