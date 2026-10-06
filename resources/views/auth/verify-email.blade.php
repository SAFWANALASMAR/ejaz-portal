<x-layouts.auth :title="__('account.verify_email.title')" :intro="__('account.verify_email.intro')">
    @if (session('status') === 'verification-link-sent')
        <x-alert type="success" class="mb-5">{{ __('account.verify_email.sent') }}</x-alert>
    @endif

    <form method="POST" action="{{ route('verification.send') }}">
        @csrf
        <x-button class="w-full">{{ __('account.verify_email.resend') }}</x-button>
    </form>
</x-layouts.auth>
