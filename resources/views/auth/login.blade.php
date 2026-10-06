<x-layouts.auth :title="__('account.login.title')" :intro="__('account.login.intro')">
    <form method="POST" action="{{ route('login.store') }}" class="space-y-5" novalidate>
        @csrf

        <x-field name="login" :label="__('account.login.identifier')" autocomplete="username" dir="ltr" required autofocus />

        <x-field name="password" type="password" :label="__('account.login.password')" autocomplete="current-password" dir="ltr" required />

        <div class="flex items-center justify-between gap-4 text-sm">
            <label class="flex items-center gap-2">
                <input type="checkbox" name="remember" value="1" class="size-4 rounded border-line text-primary focus:ring-primary">
                {{ __('account.login.remember') }}
            </label>
            <a href="{{ route('password.request') }}" class="text-primary hover:underline">{{ __('account.login.forgot') }}</a>
        </div>

        <x-button class="w-full">{{ __('account.login.submit') }}</x-button>
    </form>

    <x-slot:footer>
        {{ __('account.login.no_account') }}
        <a href="{{ route('register') }}" class="font-semibold text-primary hover:underline">{{ __('account.login.create_account') }}</a>
    </x-slot:footer>
</x-layouts.auth>
