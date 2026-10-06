<x-layouts.auth :title="__('account.register.title')" :intro="__('account.register.intro')">
    <form method="POST" action="{{ route('register.store') }}" class="space-y-5" novalidate>
        @csrf

        <p class="text-sm text-muted">{{ __('site.required_hint') }}</p>

        <x-field name="name" :label="__('account.register.name')" autocomplete="name" maxlength="120" required autofocus />

        <x-field name="email" type="email" :label="__('account.register.email')" autocomplete="email" dir="ltr" required />

        <x-field name="phone" type="tel" :label="__('account.register.phone')" :hint="__('account.register.phone_hint')"
            autocomplete="tel" inputmode="tel" dir="ltr" placeholder="05XXXXXXXX" required />

        <x-field name="password" type="password" :label="__('account.register.password')" :hint="__('account.register.password_hint')"
            autocomplete="new-password" dir="ltr" required />

        <x-field name="password_confirmation" type="password" :label="__('account.register.password_confirmation')"
            autocomplete="new-password" dir="ltr" required />

        <div class="space-y-1.5">
            <label class="flex items-start gap-3 text-sm leading-6">
                <input type="checkbox" name="privacy" value="1" @checked(old('privacy')) required
                    class="mt-1 size-4 rounded border-line text-primary focus:ring-primary"
                    @error('privacy') aria-invalid="true" aria-describedby="privacy-error" @enderror>
                <span>
                    {{ __('account.register.privacy') }}
                    @if (Route::has('privacy'))
                        <a href="{{ route('privacy') }}" target="_blank" class="text-primary underline">{{ __('account.register.privacy_link') }}</a>
                    @endif
                </span>
            </label>
            @error('privacy')
                <p id="privacy-error" class="text-sm font-medium text-red-700">{{ $message }}</p>
            @enderror
        </div>

        <x-button class="w-full">{{ __('account.register.submit') }}</x-button>
    </form>

    <x-slot:footer>
        {{ __('account.register.have_account') }}
        <a href="{{ route('login') }}" class="font-semibold text-primary hover:underline">{{ __('account.login.title') }}</a>
    </x-slot:footer>
</x-layouts.auth>
