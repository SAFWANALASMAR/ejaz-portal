<x-layouts.auth :title="__('account.verify_email.title')" :intro="__('account.email_code.intro', ['email' => auth()->user()?->email])">
    {{-- رمز من ستة أرقام إلى البريد (decisions/2026-11-06-email-otp.md). و«أرسلنا الرمز» من رسائل الجلسة في القالب (components/flash) --}}
    <form method="POST" action="{{ route('account.email.verify.store') }}" class="space-y-5" novalidate>
        @csrf
        <x-field name="code" :label="__('account.phone.code')" inputmode="numeric" autocomplete="one-time-code"
            maxlength="6" dir="ltr" required autofocus />
        <x-button class="w-full">{{ __('account.phone.submit') }}</x-button>
    </form>

    <form method="POST" action="{{ route('account.email.send') }}" class="mt-4">
        @csrf
        <x-button variant="link">{{ __('account.email_code.resend') }}</x-button>
    </form>

    <x-or-divider class="my-5" />

    <x-button variant="outline" :href="route('account.dashboard')" class="w-full">{{ __('account.email_code.later') }}</x-button>
</x-layouts.auth>
