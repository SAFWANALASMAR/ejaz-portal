{{-- «طلباتي»: طلبات المستخدم بحالاتها ورقمها المرجعي، الأحدث أولاً --}}
@php
    use App\Support\Dates;
@endphp

<x-layouts.account :title="__('services.requests.title')">
    @if ($requests->isEmpty())
        <x-card>
            <x-empty-state icon="inbox-stack" :message="__('services.requests.empty')" />
            @if (Route::has('services.index'))
                <x-button class="mt-4" variant="outline" :href="route('services.index')" arrow>{{ __('services.requests.browse') }}</x-button>
            @endif
        </x-card>
    @else
        <ul class="space-y-3">
            @foreach ($requests as $item)
                <li>
                    <a href="{{ route('account.requests.show', $item) }}" wire:navigate
                        class="acct-row group">
                        <x-icon-tile icon="inbox-stack" size="sm" class="shrink-0" />
                        <span class="min-w-0 flex-1 basis-48">
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
