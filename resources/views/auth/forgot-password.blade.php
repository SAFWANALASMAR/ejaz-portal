{{-- الاستعادة برابط على البريد، أو برمز على الجوال متى كانت الرسائل النصية جاهزة (قرار صفوان، 20 أكتوبر 2026) --}}
@php($byPhone = \App\Actions\Accounts\ResetPasswordByPhone::available())

<x-layouts.auth :title="__('account.forgot.title')" :intro="__($byPhone ? 'account.password_reset.choose' : 'account.forgot.intro')">
    <section aria-labelledby="reset-by-email">
        @if ($byPhone)
            <h2 id="reset-by-email" class="mb-3 text-base font-semibold text-ink">{{ __('account.password_reset.by_email') }}</h2>
        @endif

        <form method="POST" action="{{ route('password.email') }}" class="space-y-4" novalidate>
            @csrf

            <x-field name="email" type="email" :label="__('account.register.email')" :placeholder="__('account.register.email')" icon="envelope" hide-label
                autocomplete="email" dir="auto" required autofocus />

            <x-bot-challenge action="forgot" />

            <x-button class="w-full">{{ __('account.forgot.submit') }}</x-button>
        </form>
    </section>

    @if ($byPhone)
        <x-or-divider class="my-5" />

        <section aria-labelledby="reset-by-phone">
            <h2 id="reset-by-phone" class="mb-1 text-base font-semibold text-ink">{{ __('account.password_reset.by_phone') }}</h2>
            <p class="mb-3 text-sm text-muted">{{ __('account.password_reset.phone_intro') }}</p>

            <form method="POST" action="{{ route('password.phone.send') }}" class="space-y-4" novalidate>
                @csrf

                <x-field name="phone" type="tel" :label="__('account.register.phone')" :placeholder="__('account.register.phone_placeholder')" icon="device-phone-mobile" hide-label
                    autocomplete="tel" inputmode="tel" dir="auto" required />

                <x-bot-challenge action="forgot" />

                <x-button variant="outline" class="w-full">{{ __('account.password_reset.send_code') }}</x-button>
            </form>
        </section>
    @endif

    {{-- من لا بريد موثقاً له ولا جوالاً تعيد له الإدارة كلمة المرور من اللوحة (ملاحظات المدير التنفيذي، 25 أكتوبر 2026) --}}
    <p class="mt-5 rounded-xl bg-surface px-4 py-3 text-sm leading-6 text-muted">
        {{ __('account.forgot.no_contact') }}
        <a href="{{ route('contact') }}" class="font-medium text-primary underline">{{ __('account.forgot.contact') }}</a>
    </p>

    <x-or-divider class="my-5" />

    <x-button variant="outline" :href="route('login')" class="w-full">{{ __('account.forgot.back_to_login') }}</x-button>
</x-layouts.auth>
