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
                        <label class="cursor-pointer rounded-full border border-line px-3 py-2 text-center font-semibold text-ink has-checked:border-primary has-checked:bg-primary has-checked:text-white">
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

        <x-button class="w-full" size="lg" arrow>{{ __('payments.donate.submit') }}</x-button>

        <p class="flex items-start gap-2 text-xs leading-5 text-muted">
            {{ svg('heroicon-o-lock-closed', 'mt-0.5 size-4 shrink-0 text-primary', ['aria-hidden' => 'true']) }}
            {{ __('payments.donate.secure_note') }}
        </p>
    </form>
</x-layouts.auth>
