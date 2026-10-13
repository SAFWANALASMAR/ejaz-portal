<x-layouts.account :title="__('account.profile.title')">
    @include('account.partials.profile-tabs')

    <x-card>
        <p class="leading-7 text-ink">{{ __('account.data.intro') }}</p>
    </x-card>

    <x-card>
        <h2 class="text-lg font-semibold text-ink">{{ __('account.data.export') }}</h2>
        <p class="mt-1 text-muted">{{ __('account.data.export_hint') }}</p>
        <x-button :href="route('account.data.export')" variant="secondary" class="mt-4">{{ __('site.download') }}</x-button>
    </x-card>

    <x-card>
        <h2 class="text-lg font-semibold text-ink">{{ __('account.data.deletion') }}</h2>

        @if ($user->deletion_requested_at)
            <x-alert type="info" class="mt-4">
                {{ __('account.data.deletion_requested', ['date' => \App\Support\Dates::display($user->deletion_requested_at)]) }}
            </x-alert>
        @else
            <p class="mt-1 text-muted">{{ __('account.data.deletion_hint') }}</p>

            <form method="POST" action="{{ route('account.data.deletion') }}" class="mt-4 max-w-md space-y-4" novalidate>
                @csrf
                <x-field name="password" type="password" :label="__('account.profile.current_password')" autocomplete="current-password" dir="ltr" required />
                <x-button variant="danger">{{ __('account.data.deletion_submit') }}</x-button>
            </form>
        @endif
    </x-card>
</x-layouts.account>
