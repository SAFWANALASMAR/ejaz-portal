{{-- «طلباتي»: طلبات المستخدم بحالاتها ورقمها المرجعي، الأحدث أولاً --}}
@php
    use App\Support\Dates;
@endphp

<x-layouts.account :title="__('services.requests.title')">
    @if ($requests->isEmpty())
        <x-card>
            <p class="text-muted">{{ __('services.requests.empty') }}</p>
            @if (Route::has('services.index'))
                <x-button class="mt-4" variant="outline" :href="route('services.index')" arrow>{{ __('services.requests.browse') }}</x-button>
            @endif
        </x-card>
    @else
        <ul class="space-y-3">
            @foreach ($requests as $item)
                <li>
                    <a href="{{ route('account.requests.show', $item) }}" wire:navigate
                        class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-line/70 bg-white p-5 shadow-xs transition hover:border-primary/40">
                        <span class="min-w-0">
                            <span class="block font-semibold text-ink">{{ $item->opening->title }}</span>
                            <span class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted">
                                <span>{{ $item->opening->kind->getLabel() }}</span>
                                <span>{{ __('services.requests.reference') }}: <span dir="ltr">{{ $item->reference }}</span></span>
                                <span>{{ $item->submitted_at ? Dates::display($item->submitted_at) : __('services.requests.not_submitted') }}</span>
                            </span>
                        </span>
                        @include('account.requests.status', ['status' => $item->status])
                    </a>
                </li>
            @endforeach
        </ul>

        {{ $requests->links('partials.pagination') }}
    @endif
</x-layouts.account>
