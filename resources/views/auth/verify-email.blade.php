<x-layouts.auth :title="__('account.verify_email.title')" :intro="__('account.verify_email.intro')">
    {{-- «أرسلنا الرابط» من رسائل الجلسة في القالب (components/flash) --}}
    <form method="POST" action="{{ route('verification.send') }}">
        @csrf
        <x-button class="w-full">{{ __('account.verify_email.resend') }}</x-button>
    </form>

    <x-or-divider class="my-5" />

    <x-button variant="outline" :href="route('account.dashboard')" class="w-full">{{ __('site.my_account') }}</x-button>
</x-layouts.auth>
