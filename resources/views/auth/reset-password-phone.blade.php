{{-- كلمة مرور جديدة برمز الجوال (ResetPasswordByPhone). والرقم من الجلسة، يُعرض ولا يُعدَّل هنا --}}
<x-layouts.auth :title="__('account.password_reset.title')" :intro="__('account.password_reset.code_intro', ['phone' => $phone])">
    <form method="POST" action="{{ route('password.phone.update') }}" class="space-y-4" novalidate>
        @csrf

        <x-field name="code" :label="__('account.password_reset.code')" :placeholder="__('account.password_reset.code')" icon="key"
            autocomplete="one-time-code" inputmode="numeric" maxlength="6" dir="ltr" required autofocus />

        <x-field name="password" type="password" :label="__('account.profile.new_password')" :placeholder="__('account.profile.new_password')" icon="lock-closed" hide-label
            :hint="__('account.register.password_hint')" autocomplete="new-password" required />

        <x-password-rules />

        <x-field name="password_confirmation" type="password" :label="__('account.register.password_confirmation')" :placeholder="__('account.register.password_confirmation')"
            icon="lock-closed" hide-label autocomplete="new-password" required />

        <x-button class="w-full">{{ __('account.reset.submit') }}</x-button>
    </form>

    <x-or-divider class="my-5" />

    <x-button variant="outline" :href="route('password.request')" class="w-full">{{ __('account.password_reset.again') }}</x-button>
</x-layouts.auth>
