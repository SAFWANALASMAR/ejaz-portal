{{--
    التبرع: بطاقة في المنتصف كصفحة الدخول (الصورة 04). مبالغ مقترحة من اللوحة أو مبلغ يكتبه المتبرع،
    وبريده للإيصال إن لم يكن له حساب، ثم التحويل إلى صفحة الدفع عند البوابة.
--}}
<x-layouts.auth :title="__('payments.donate.title')" :intro="$intro ?: __('payments.donate.intro')" :image="false" :noindex="false">
    <form method="POST" action="{{ route('donate.store') }}" class="space-y-5" novalidate>
        @csrf

        @if ($presets !== [])
            <fieldset>
                <legend class="mb-2 block text-sm font-semibold text-ink">{{ __('payments.donate.presets') }}</legend>
                <div class="grid grid-cols-3 gap-2">
                    @foreach ($presets as $preset)
                        <label class="cursor-pointer rounded-full border border-line px-3 py-2 text-center font-semibold text-ink has-checked:border-primary has-checked:bg-primary has-checked:text-white has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-accent">
                            <input type="radio" name="preset" value="{{ $preset }}" class="sr-only" @checked((string) old('preset') === (string) $preset)>
                            {{ number_format($preset) }} <span class="text-xs">{{ __('payments.currencies.SAR') }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
        @endif

        <x-field name="amount" :label="$presets !== [] ? __('payments.donate.other_amount') : __('payments.fields.amount')" icon="banknotes"
            :hint="__('payments.donate.amount_hint')" inputmode="decimal" dir="ltr" autocomplete="off" />

        @if ($user === null)
            <x-field name="email" type="email" :label="__('payments.fields.email')" icon="envelope" :hint="__('payments.donate.email_hint')"
                dir="ltr" autocomplete="email" required />
            <x-field name="name" :label="__('payments.fields.name')" icon="user" :hint="__('payments.donate.name_optional')" autocomplete="name" />
        @else
            <p class="text-sm text-muted">{{ __('payments.donate.receipt_to', ['email' => $user->email]) }}</p>
        @endif

        {{-- التحدي ضد الآليات لغير المسجل (Turnstile)، ويتحقق الخادم من رمزه --}}
        @if ($challengeKey ?? null)
            <div class="cf-turnstile" data-sitekey="{{ $challengeKey }}" data-action="donation" data-language="ar"></div>
            <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer nonce="{{ Vite::cspNonce() }}"></script>
        @endif

        <x-button class="w-full" size="lg" arrow>{{ __('payments.donate.submit') }}</x-button>

        <p class="flex items-start gap-2 text-xs leading-5 text-muted">
            {{ \App\Support\Icon::svg('lock-closed', 'mt-0.5 size-4 shrink-0 text-primary', ['aria-hidden' => 'true']) }}
            <span>
                {{ __('payments.donate.secure_note') }}
                @if ($challengeKey ?? null)
                    {{ __('payments.donate.privacy_challenge') }}
                @endif
            </span>
        </p>
    </form>
</x-layouts.auth>
