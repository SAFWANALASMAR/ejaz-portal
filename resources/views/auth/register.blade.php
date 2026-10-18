<x-layouts.auth :title="__('account.register.title')" :intro="__('account.register.intro')">
    <form method="POST" action="{{ route('register.store') }}" class="space-y-4" novalidate>
        @csrf

        <x-field name="name" :label="__('account.register.name')" :placeholder="__('account.register.name')" icon="user" hide-label
            autocomplete="name" maxlength="120" required autofocus />

        <x-field name="email" type="email" :label="__('account.register.email')" :placeholder="__('account.register.email')" icon="envelope" hide-label
            autocomplete="email" dir="auto" required />

        <x-field name="phone" type="tel" :label="__('account.register.phone')" :placeholder="__('account.register.phone_placeholder')" icon="device-phone-mobile" hide-label
            :hint="__('account.register.phone_hint')" autocomplete="tel" inputmode="tel" dir="auto" required />

        <x-field name="password" type="password" :label="__('account.register.password')" :placeholder="__('account.register.password')" icon="lock-closed" hide-label
            :hint="__('account.register.password_hint')" autocomplete="new-password" required />

        <x-field name="password_confirmation" type="password" :label="__('account.register.password_confirmation')" :placeholder="__('account.register.password_confirmation')"
            icon="lock-closed" hide-label autocomplete="new-password" required />

        <div class="space-y-1.5">
            <label class="flex items-start gap-3 text-sm leading-6 text-ink">
                <input type="checkbox" name="privacy" value="1" @checked(old('privacy')) required
                    class="mt-1 size-4 shrink-0 rounded border-line text-primary focus:ring-primary"
                    @error('privacy') aria-invalid="true" aria-describedby="privacy-error" @enderror>
                <span>
                    {{ __('account.register.privacy') }}
                    @if (\App\Models\Page::isPublishedSlug(\App\Models\Page::PRIVACY))
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

    <x-or-divider class="my-5" />

    <p class="mb-3 text-center text-sm text-muted">{{ __('account.register.have_account') }}</p>
    <x-button variant="outline" :href="route('login')" class="w-full">{{ __('account.login.title') }}</x-button>
</x-layouts.auth>
