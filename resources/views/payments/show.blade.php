{{--
    حالة عملية الدفع بعد الرجوع من البوابة: الحالة كما قرأها الخادم من البوابة، والمبلغ، ورقم الإيصال.
    لا بيانات المتبرع، لأن الصفحة برابط المعرّف العشوائي وقد تُشارَك.
--}}
@php
    use App\Enums\PaymentStatus;
    use App\Support\Dates;

    $icon = match ($payment->status) {
        PaymentStatus::Paid => ['check-circle', 'text-emerald-700 bg-emerald-50'],
        PaymentStatus::Failed => ['x-circle', 'text-red-700 bg-red-50'],
        PaymentStatus::Refunded => ['arrow-uturn-left', 'text-muted bg-surface'],
        PaymentStatus::Pending => ['clock', 'text-amber-700 bg-amber-50'],
    };
@endphp

<x-layouts.auth :title="__('payments.show.title')" :image="false">
    <div class="text-center">
        <span class="inline-flex size-16 items-center justify-center rounded-full {{ $icon[1] }}">{{ svg('heroicon-o-'.$icon[0], 'size-9') }}</span>
        <p class="mt-4 text-2xl font-bold text-ink">{{ $payment->status->getLabel() }}</p>
        <p class="mt-2 leading-7 text-muted">{{ __('payments.show.'.$payment->status->value.'_hint') }}</p>
    </div>

    <dl class="mt-6 divide-y divide-line rounded-2xl border border-line">
        <div class="flex items-center justify-between gap-4 px-4 py-3">
            <dt class="text-sm text-muted">{{ __('payments.fields.amount') }}</dt>
            <dd class="font-bold text-ink"><span dir="ltr">{{ $payment->formattedAmount() }}</span> {{ __('payments.currencies.'.$payment->currency) }}</dd>
        </div>
        <div class="flex items-center justify-between gap-4 px-4 py-3">
            <dt class="text-sm text-muted">{{ __('payments.fields.purpose') }}</dt>
            <dd class="font-semibold text-ink">{{ $payment->purpose->getLabel() }}</dd>
        </div>
        @if ($payment->receipt_number)
            <div class="flex items-center justify-between gap-4 px-4 py-3">
                <dt class="text-sm text-muted">{{ __('payments.show.receipt') }}</dt>
                <dd class="font-semibold text-primary" dir="ltr">{{ $payment->receipt_number }}</dd>
            </div>
            <div class="flex items-center justify-between gap-4 px-4 py-3">
                <dt class="text-sm text-muted">{{ __('payments.show.date') }}</dt>
                <dd class="text-ink">{{ Dates::display($payment->paid_at, withTime: true) }}</dd>
            </div>
        @endif
    </dl>

    <div class="mt-6 flex flex-col gap-3">
        @if ($donationsEnabled && ($payment->status === PaymentStatus::Pending || $payment->status === PaymentStatus::Failed))
            <x-button :href="route('donate')" class="w-full">{{ __('payments.show.retry') }}</x-button>
        @endif
        @auth
            <x-button :href="route('account.payments')" variant="outline" class="w-full">{{ __('payments.show.my_payments') }}</x-button>
        @endauth
        <x-button :href="route('home')" variant="link" class="justify-center">{{ __('payments.show.home') }}</x-button>
    </div>
</x-layouts.auth>
