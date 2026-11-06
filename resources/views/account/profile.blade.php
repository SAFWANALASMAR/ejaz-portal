<x-layouts.account :title="__('account.profile.title')">
    @include('account.partials.profile-tabs')

    @include('account.partials.profile-summary')

    {{-- رسالة الحفظ وتغيير كلمة المرور من رسائل الجلسة في القالب (components/flash) --}}

    <x-card>
        <x-account-section-title icon="identification-card">{{ __('account.profile.info') }}</x-account-section-title>

        <form method="POST" action="{{ route('user-profile-information.update') }}" class="grid gap-5 md:grid-cols-2" novalidate>
            @csrf
            @method('PUT')

            <x-field name="name" :label="__('account.register.name')" :value="$user->name" bag="updateProfileInformation" autocomplete="name" required />
            @if ($user->username !== null)
                <x-field name="username_display" :label="__('account.profile.username')" :value="$user->username" :hint="__('account.profile.username_hint')"
                    dir="ltr" readonly disabled />
            @endif
            {{-- البريد والجوال اختياريان (ملاحظات المدير التنفيذي، 25 أكتوبر 2026) --}}
            <x-field name="email" type="email" :label="__('account.register.email')" :value="$user->email" :hint="__('account.register.email_hint')" bag="updateProfileInformation" autocomplete="email" dir="ltr" />
            <x-field name="phone" type="tel" :label="__('account.register.phone')" :value="\App\Support\SaudiPhone::display($user->phone)" :hint="__('account.register.phone_hint')" bag="updateProfileInformation"
                autocomplete="tel" inputmode="tel" dir="ltr" />
            <x-field name="current_password" id="profile-current-password" type="password" :label="__('account.profile.confirm_password')" :hint="__('account.profile.confirm_password_hint')"
                bag="updateProfileInformation" autocomplete="current-password" dir="ltr" class="md:col-span-2" />

            <div class="md:col-span-2">
                <x-button>{{ __('site.save') }}</x-button>
            </div>
        </form>

        {{-- التوثيق باختيار صاحب الحساب ولو كان مطفأً شرطاً: به يستعيد كلمة المرور بنفسه --}}
        @if (($user->email !== null && ! $user->hasVerifiedEmail()) || ($user->phone !== null && ! $user->hasVerifiedPhone()))
            <div class="mt-5 flex flex-wrap items-center gap-3 border-t border-line pt-5 text-sm">
                @if ($user->email !== null && ! $user->hasVerifiedEmail())
                    <x-button variant="outline" size="sm" :href="route('verification.notice')">{{ __('account.profile.verify_email') }}</x-button>
                @endif
                @if ($user->phone !== null && ! $user->hasVerifiedPhone())
                    <x-button variant="outline" size="sm" :href="route('account.phone.verify')">{{ __('account.profile.verify_phone') }}</x-button>
                @endif
            </div>
        @endif
    </x-card>

    {{-- «لا تحفظ قراءاتي» (قرار صفوان، 2 نوفمبر 2026 — docs/decisions/2026-11-01-my-reading.md) --}}
    <x-card>
        <x-account-section-title icon="book-open" id="reading-tracking">{{ __('reading.tracking.title') }}</x-account-section-title>
        <form method="POST" action="{{ route('account.my-reading.tracking') }}" class="grid gap-4" data-reading-tracking-form>
            @csrf
            <input type="hidden" name="disabled" value="0">
            <label class="flex items-start gap-3">
                <input type="checkbox" name="disabled" value="1" @checked($user->reading_tracking_disabled_at !== null)
                    class="mt-1 size-5 shrink-0 rounded border-line text-primary focus:ring-primary">
                <span>
                    <span class="block font-semibold text-ink">{{ __('reading.tracking.label') }}</span>
                    <span class="mt-1 block text-sm text-muted">{{ __('reading.tracking.hint') }}</span>
                </span>
            </label>
            <div>
                <x-button>{{ __('site.save') }}</x-button>
            </div>
        </form>
    </x-card>

    <x-card>
        <x-account-section-title icon="key" id="password">{{ __('account.profile.password') }}</x-account-section-title>

        <form method="POST" action="{{ route('user-password.update') }}" class="grid gap-5 md:grid-cols-2" novalidate>
            @csrf
            @method('PUT')

            <x-field name="current_password" type="password" :label="__('account.profile.current_password')" bag="updatePassword"
                autocomplete="current-password" dir="ltr" required class="md:col-span-2" />
            <x-field name="password" type="password" :label="__('account.profile.new_password')" :hint="__('account.register.password_hint')" bag="updatePassword"
                autocomplete="new-password" dir="ltr" required />
            <x-field name="password_confirmation" type="password" :label="__('account.register.password_confirmation')" bag="updatePassword"
                autocomplete="new-password" dir="ltr" required />
            <x-password-rules class="md:col-span-2" />

            <div class="md:col-span-2">
                <x-button>{{ __('site.save') }}</x-button>
            </div>
        </form>
    </x-card>
</x-layouts.account>
