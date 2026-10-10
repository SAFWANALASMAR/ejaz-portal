{{--
    نموذج الطلب: بيانات مقدم الطلب من حسابه، ثم حقول الفرصة، ثم «حفظ مسودة» و«إرسال الطلب».
    يُستعمل للتقديم الجديد ولتعديل المسودة أو استكمال الطلب.
--}}
@php
    use App\Support\SaudiPhone;

    $user = auth()->user();
    $action = $serviceRequest ? route('account.requests.update', $serviceRequest) : route('services.apply.store', $opening->slug);
@endphp

<form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="space-y-6" novalidate>
    @csrf
    @if ($serviceRequest)
        @method('PUT')
    @endif

    <x-card>
        <h2 class="text-lg font-semibold text-ink">{{ __('services.form.applicant') }}</h2>
        <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-3">
            <div>
                <dt class="text-muted">{{ __('account.register.name') }}</dt>
                <dd class="mt-1 font-medium text-ink">{{ $user->name }}</dd>
            </div>
            <div>
                <dt class="text-muted">{{ __('account.register.email') }}</dt>
                <dd class="mt-1 font-medium text-ink" dir="ltr">{{ $user->email }}</dd>
            </div>
            <div>
                <dt class="text-muted">{{ __('account.register.phone') }}</dt>
                <dd class="mt-1 font-medium text-ink" dir="ltr">{{ SaudiPhone::display($user->phone) }}</dd>
            </div>
        </dl>
        <p class="mt-4 text-sm text-muted">{{ __('services.form.applicant_hint') }}</p>
    </x-card>

    <x-card>
        <h2 class="mb-5 text-lg font-semibold text-ink">{{ __('services.form.answers') }}</h2>
        @error('answers')
            <x-alert type="error" class="mb-5">{{ $message }}</x-alert>
        @enderror
        <div class="grid gap-5 md:grid-cols-2">
            @include('account.requests.fields')
        </div>
    </x-card>

    <div class="flex flex-wrap items-center gap-3">
        <x-button name="intent" value="submit">{{ __('services.form.submit') }}</x-button>
        <x-button name="intent" value="save" variant="outline">{{ __('services.form.save') }}</x-button>
        <p class="w-full text-sm text-muted">{{ __('services.form.submit_hint') }}</p>
    </div>
</form>
