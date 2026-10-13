{{-- صفحة الدفع التجريبية للبوابة الوهمية: البيئة المحلية فقط، معلَّمة بوضوح --}}
<x-layouts.auth :title="__('payments.fake.title')" :intro="__('payments.fake.note')" :image="false">
    <x-alert type="warning">{{ __('payments.fake.note') }}</x-alert>

    <p class="mt-6 text-center text-3xl font-bold text-ink">
        <span dir="ltr">{{ $payment->formattedAmount() }}</span> {{ __('payments.currencies.'.$payment->currency) }}
    </p>
    <p class="mt-1 text-center text-sm text-muted" dir="ltr">{{ $result->reference }}</p>

    <form method="POST" action="{{ route('payments.fake-checkout.pay', $payment) }}" class="mt-6">
        @csrf
        <x-button class="w-full" size="lg">{{ __('payments.fake.pay') }}</x-button>
    </form>

    <form method="POST" action="{{ route('payments.fake-checkout.fail', $payment) }}" class="mt-3">
        @csrf
        <x-button variant="outline" class="w-full">{{ __('payments.fake.fail') }}</x-button>
    </form>
</x-layouts.auth>
