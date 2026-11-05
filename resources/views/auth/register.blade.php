{{-- على الحاسوب عمودان (ملاحظات المدير التنفيذي، 4 نوفمبر 2026): الاسم والجوال والبريد، ثم كلمة المرور وشروطها وتأكيدها،
     فتقصر البطاقة. وعلى الجوال عمود واحد بالترتيب نفسه. ونموذج أقصر (إضافة المشرف 01:14): بلا تلميح كلمة المرور (شروطها تحتها
     بنصها) ولا تلميح البريد («(اختياري)» في نص الحقل) --}}
<x-layouts.auth :title="__('account.register.title')" :intro="__('account.register.intro')" wide>
    <form method="POST" action="{{ route('register.store') }}" class="space-y-4 lg:space-y-3" novalidate>
        @csrf

        <div class="space-y-4 lg:grid lg:grid-cols-2 lg:items-start lg:gap-x-6 lg:space-y-0" data-register-columns>
            <div class="space-y-4 lg:space-y-3">
                <x-field name="name" :label="__('account.register.name')" :placeholder="__('account.register.name')" icon="user" hide-label
                    autocomplete="name" maxlength="120" required autofocus />

                {{-- الدخول بالجوال، والبريد اختياري، ولا اسم مستخدم للحساب الجديد (قرار صفوان، 5 نوفمبر 2026) --}}
                <x-field name="phone" type="tel" :label="__('account.register.phone')" :placeholder="__('account.register.phone_placeholder')" icon="device-phone-mobile" hide-label
                    :hint="__('account.register.phone_hint')" autocomplete="tel" inputmode="tel" dir="auto" required />

                {{-- بلا تلميح: «(اختياري)» في نص الحقل (نموذج أقصر، إضافة المشرف 01:14) --}}
                <x-field name="email" type="email" :label="__('account.register.email')" :placeholder="__('account.register.email_placeholder')" icon="envelope" hide-label
                    autocomplete="email" dir="auto" />
            </div>

            <div class="space-y-4 lg:space-y-3">
                <x-field name="password" type="password" :label="__('account.register.password')" :placeholder="__('account.register.password')" icon="lock-closed" hide-label
                    autocomplete="new-password" required />

                <x-password-rules compact />

                <x-field name="password_confirmation" type="password" :label="__('account.register.password_confirmation')" :placeholder="__('account.register.password_confirmation')"
                    icon="lock-closed" hide-label autocomplete="new-password" required />
            </div>
        </div>

        <x-bot-challenge action="register" />

        {{-- على الحاسوب الموافقة وزر الإنشاء في صف واحد، فتدخل البطاقة كاملة تحت الرأس في 1366×768 (المدير عبر المشرف، 5 نوفمبر 2026) --}}
        <div class="space-y-4 lg:flex lg:items-center lg:gap-6 lg:space-y-0">
            <div class="space-y-1.5 lg:flex-1">
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

            <x-button class="w-full lg:w-auto lg:shrink-0 lg:px-10">{{ __('account.register.submit') }}</x-button>
        </div>
    </form>

    <x-or-divider class="my-5 lg:hidden" />

    {{-- على الحاسوب «لديك حساب؟» وزر الدخول في سطر واحد، بخط فاصل بلا «أو» --}}
    <div class="lg:mt-3 lg:flex lg:items-center lg:justify-center lg:gap-4 lg:border-t lg:border-line/70 lg:pt-3">
        <p class="mb-3 text-center text-sm text-muted lg:mb-0">{{ __('account.register.have_account') }}</p>
        <x-button variant="outline" :href="route('login')" class="w-full lg:w-auto lg:px-10">{{ __('account.login.title') }}</x-button>
    </div>
</x-layouts.auth>
