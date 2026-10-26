<x-layouts.account :title="__('account.profile.title')">
    @include('account.partials.profile-tabs')

    {{-- رسالة الحفظ وتغيير كلمة المرور من رسائل الجلسة في القالب (components/flash) --}}

    <x-card>
        <h2 class="mb-5 text-lg font-semibold text-ink">{{ __('account.profile.info') }}</h2>

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
                    <form method="POST" action="{{ route('verification.send') }}">
                        @csrf
                        <x-button variant="outline" size="sm">{{ __('account.profile.verify_email') }}</x-button>
                    </form>
                @endif
                @if ($user->phone !== null && ! $user->hasVerifiedPhone())
                    <x-button variant="outline" size="sm" :href="route('account.phone.verify')">{{ __('account.profile.verify_phone') }}</x-button>
                @endif
            </div>
        @endif
    </x-card>

    <x-card>
        <h2 id="password" class="mb-5 scroll-mt-24 text-lg font-semibold text-ink">{{ __('account.profile.password') }}</h2>

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
