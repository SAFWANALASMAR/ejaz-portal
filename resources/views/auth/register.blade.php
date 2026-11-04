{{-- على الحاسوب عمودان (ملاحظات المدير التنفيذي، 4 نوفمبر 2026): الاسم واسم المستخدم والبريد والجوال، ثم كلمة المرور وشروطها وتأكيدها،
     فتقصر البطاقة. وعلى الجوال عمود واحد بالترتيب نفسه. ونموذج أقصر (إضافة المشرف 01:14): بلا تلميح كلمة المرور (شروطها تحتها
     بنصها) ولا تلميح البريد (ملاحظة «اختياريان» فوقه تقول ما فيه) --}}
<x-layouts.auth :title="__('account.register.title')" :intro="__('account.register.intro')" wide>
    <form method="POST" action="{{ route('register.store') }}" class="space-y-4" novalidate>
        @csrf

        <div class="space-y-4 lg:grid lg:grid-cols-2 lg:items-start lg:gap-x-6 lg:space-y-0" data-register-columns>
            <div class="space-y-4">
                <x-field name="name" :label="__('account.register.name')" :placeholder="__('account.register.name')" icon="user" hide-label
                    autocomplete="name" maxlength="120" required autofocus />

                {{-- الدخول باسم المستخدم، والبريد والجوال اختياريان (ملاحظات المدير التنفيذي، 25 أكتوبر 2026) --}}
                <x-field name="username" :label="__('account.register.username')" :placeholder="__('account.register.username')" icon="at-symbol" hide-label
                    :hint="__('account.register.username_hint')" autocomplete="username" autocapitalize="none" spellcheck="false" dir="ltr"
                    minlength="3" maxlength="30" required />

                <fieldset class="space-y-4 rounded-2xl border border-line/70 p-4">
                    <legend class="px-1 text-sm leading-6 text-muted">{{ __('account.register.optional_note') }}</legend>

                    <x-field name="email" type="email" :label="__('account.register.email')" :placeholder="__('account.register.email_placeholder')" icon="envelope" hide-label
                        autocomplete="email" dir="auto" />

                    <x-field name="phone" type="tel" :label="__('account.register.phone')" :placeholder="__('account.register.phone_placeholder')" icon="device-phone-mobile" hide-label
                        :hint="__('account.register.phone_hint')" autocomplete="tel" inputmode="tel" dir="auto" />
                </fieldset>
            </div>

            <div class="space-y-4">
                <x-field name="password" type="password" :label="__('account.register.password')" :placeholder="__('account.register.password')" icon="lock-closed" hide-label
                    autocomplete="new-password" required />

                <x-password-rules />

                <x-field name="password_confirmation" type="password" :label="__('account.register.password_confirmation')" :placeholder="__('account.register.password_confirmation')"
                    icon="lock-closed" hide-label autocomplete="new-password" required />
            </div>
        </div>

        <div class="space-y-1.5">
            <label class="flex items-start gap-3 text-sm leading-6 text-ink">
                <input type="checkbox" name="privacy" value="1" @checked(old('privacy')) required
                    class="mt-1 size-4 shrink-0 rounded border-line text-primary focus:ring-primary"
                    @error('privacy') aria-invalid="true" aria-describedby="privacy-error" @enderror>
                <span>
                    {{ __('account.register.privacy') }}
                    @if (\App\Models\Page::isPublishedSlug(\App\Models\Page::PRIVACY))
                        <a href="{{ route('privacy') }}" target="_blank" class="text-primary underline">{{ __('account.register.privacy_link') }}</a>
                    @endif
                </span>
            </label>
            @error('privacy')
                <p id="privacy-error" class="text-sm font-medium text-red-700">{{ $message }}</p>
            @enderror
        </div>

        <x-bot-challenge action="register" />

        <x-button class="w-full">{{ __('account.register.submit') }}</x-button>
    </form>

    <x-or-divider class="my-5 lg:my-3" />

    {{-- على الحاسوب «لديك حساب؟» وزر الدخول في سطر واحد --}}
    <div class="lg:flex lg:items-center lg:justify-center lg:gap-4">
        <p class="mb-3 text-center text-sm text-muted lg:mb-0">{{ __('account.register.have_account') }}</p>
        <x-button variant="outline" :href="route('login')" class="w-full lg:w-auto lg:px-10">{{ __('account.login.title') }}</x-button>
    </div>
</x-layouts.auth>
