{{-- التسجيل في برنامج: الحقول الإضافية التي حددها المسؤول، ثم التأكيد --}}
<x-layouts.account :title="__('programs.register.title', ['program' => $program->title])">
    <form method="POST" action="{{ route('programs.register.store', $program->slug) }}" class="space-y-6" novalidate>
        @csrf

        @error('registration')
            <x-alert type="error">{{ $message }}</x-alert>
        @enderror

        @unless ($program->hasFreeSeat())
            <x-alert type="warning">{{ __('programs.register.waitlist_hint') }}</x-alert>
        @endunless

        <x-card>
            @if ($program->fields() === [])
                <p class="text-muted">{{ __('programs.register.no_fields') }}</p>
            @else
                <div class="grid gap-5 md:grid-cols-2">
                    @include('account.requests.fields', ['opening' => $program, 'serviceRequest' => null])
                </div>
            @endif
        </x-card>

        <x-button>{{ __('programs.register.confirm') }}</x-button>
    </form>
</x-layouts.account>
