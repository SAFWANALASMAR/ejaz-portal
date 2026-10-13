<x-layouts.account :title="__('account.profile.title')">
    @include('account.partials.profile-tabs')

    @if (session('status') === 'profile-information-updated')
        <x-alert type="success">{{ __('account.profile.saved') }}</x-alert>
    @elseif (session('status') === 'password-updated')
        <x-alert type="success">{{ __('account.profile.password_saved') }}</x-alert>
    @endif

    <x-card>
        <h2 class="mb-5 text-lg font-semibold text-ink">{{ __('account.profile.info') }}</h2>

        <form method="POST" action="{{ route('user-profile-information.update') }}" class="grid gap-5 md:grid-cols-2" novalidate>
            @csrf
            @method('PUT')

            <x-field name="name" :label="__('account.register.name')" :value="$user->name" bag="updateProfileInformation" autocomplete="name" required class="md:col-span-2" />
            <x-field name="email" type="email" :label="__('account.register.email')" :value="$user->email" bag="updateProfileInformation" autocomplete="email" dir="ltr" required />
            <x-field name="phone" type="tel" :label="__('account.register.phone')" :value="\App\Support\SaudiPhone::display($user->phone)" bag="updateProfileInformation"
                autocomplete="tel" inputmode="tel" dir="ltr" required />
            <x-field name="current_password" id="profile-current-password" type="password" :label="__('account.profile.confirm_password')" :hint="__('account.profile.confirm_password_hint')"
                bag="updateProfileInformation" autocomplete="current-password" dir="ltr" class="md:col-span-2" />

            <div class="md:col-span-2">
                <x-button>{{ __('site.save') }}</x-button>
            </div>
        </form>
    </x-card>

    <x-card>
        <h2 class="mb-5 text-lg font-semibold text-ink">{{ __('account.profile.password') }}</h2>

        <form method="POST" action="{{ route('user-password.update') }}" class="grid gap-5 md:grid-cols-2" novalidate>
            @csrf
            @method('PUT')

            <x-field name="current_password" type="password" :label="__('account.profile.current_password')" bag="updatePassword"
                autocomplete="current-password" dir="ltr" required class="md:col-span-2" />
            <x-field name="password" type="password" :label="__('account.profile.new_password')" :hint="__('account.register.password_hint')" bag="updatePassword"
                autocomplete="new-password" dir="ltr" required />
            <x-field name="password_confirmation" type="password" :label="__('account.register.password_confirmation')" bag="updatePassword"
                autocomplete="new-password" dir="ltr" required />

            <div class="md:col-span-2">
                <x-button>{{ __('site.save') }}</x-button>
            </div>
        </form>
    </x-card>
</x-layouts.account>
