{{-- «نماذجي»: المعتمد من نماذج المشاركين المحفوظة في ملف صاحب الحساب، يُفتح كورقة ويُطبع --}}
@php
    use App\Support\Dates;
@endphp

<x-layouts.account :title="__('documents.account.title')">
    @if ($entries->isEmpty())
        <div class="p-6 concept-panel">
            <x-empty-state icon="clipboard-document-list" :message="__('documents.account.empty')" />
        </div>
    @else
        <ul class="space-y-4">
            @foreach ($entries as $entry)
                <li class="acct-row">
                    <x-icon-tile icon="clipboard-document-list" size="sm" class="shrink-0" />
                    <div class="min-w-0 flex-1 basis-48">
                        <p class="font-bold text-ink">{{ $entry->version->template->title }}</p>
                        <p class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted">
                            <span>{{ __('documents.print.reference') }}: <span dir="ltr" class="font-semibold text-primary">{{ $entry->reference }}</span></span>
                            <span>{{ Dates::display($entry->approved_at ?? $entry->created_at) }}</span>
                        </p>
                    </div>
                    <x-button :href="route('account.forms.show', $entry)" variant="outline" size="sm">{{ __('documents.account.open') }}</x-button>
                </li>
            @endforeach
        </ul>

        {{ $entries->links('partials.pagination') }}
    @endif
</x-layouts.account>
