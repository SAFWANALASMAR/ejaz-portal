{{-- «مدفوعاتي»: عمليات صاحب الحساب بحالتها ومبلغها ورقم إيصالها --}}
@php
    use App\Support\Dates;
@endphp

<x-layouts.account :title="__('payments.account.title')">
    @if ($payments->isEmpty())
        <div class="rounded-2xl border border-line/70 bg-white p-6 shadow-xs">
            <x-empty-state icon="banknotes" :message="__('payments.account.empty')" />
            @if ($donationsEnabled)
                <x-button :href="route('donate')" variant="outline" class="mt-4" arrow>{{ __('payments.account.donate') }}</x-button>
            @endif
        </div>
    @else
        <ul class="space-y-4">
            @foreach ($payments as $payment)
                <li class="flex flex-col gap-3 rounded-2xl border border-line/70 bg-white p-5 shadow-xs sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="font-bold text-ink">
                            {{ $payment->purpose->getLabel() }} —
                            <span dir="ltr">{{ $payment->formattedAmount() }}</span> {{ __('payments.currencies.'.$payment->currency) }}
                        </p>
                        <p class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted">
                            <span>{{ $payment->status->getLabel() }}</span>
                            @if ($payment->receipt_number)
                                <span>{{ __('payments.show.receipt') }}: <span dir="ltr" class="font-semibold text-primary">{{ $payment->receipt_number }}</span></span>
                            @endif
                            <span>{{ Dates::display($payment->paid_at ?? $payment->created_at) }}</span>
                        </p>
                    </div>
                    <x-button :href="route('payments.return', $payment)" variant="outline" size="sm">{{ __('payments.account.details') }}</x-button>
                </li>
            @endforeach
        </ul>

        {{ $payments->links('partials.pagination') }}
    @endif
</x-layouts.account>
