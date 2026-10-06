<x-layouts.account :title="__('account.dashboard.title')">
    <x-card>
        <p class="text-xl font-semibold text-ink">{{ __('account.dashboard.welcome', ['name' => $user->name]) }}</p>
        <p class="mt-1 text-muted">{{ __('account.dashboard.intro') }}</p>
    </x-card>

    @unless ($user->hasVerifiedEmail())
        <x-alert type="warning" class="flex flex-wrap items-center justify-between gap-3">
            <span>{{ __('account.dashboard.verify_email') }}</span>
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <x-button variant="link">{{ __('account.dashboard.resend_email') }}</x-button>
            </form>
        </x-alert>
    @endunless

    @unless ($user->hasVerifiedPhone())
        <x-alert type="warning" class="flex flex-wrap items-center justify-between gap-3">
            <span>{{ __('account.dashboard.verify_phone') }}</span>
            <x-button variant="link" :href="route('account.phone.verify')">{{ __('account.dashboard.verify_now') }}</x-button>
        </x-alert>
    @endunless

    <div>
        <h2 class="mb-3 text-lg font-semibold text-ink">{{ __('account.dashboard.shortcuts') }}</h2>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach (collect(config('account.navigation'))->reject(fn ($link) => $link['route'] === 'account.dashboard')->filter(fn ($link) => Route::has($link['route']) && (! isset($link['can']) || $user->can($link['can']))) as $link)
                <a href="{{ route($link['route']) }}" class="group rounded-2xl border border-line bg-white p-5 transition hover:border-primary hover:shadow-sm">
                    <span class="text-lg font-semibold text-primary group-hover:underline">{{ __($link['label']) }}</span>
                </a>
            @endforeach
        </div>
    </div>
</x-layouts.account>
