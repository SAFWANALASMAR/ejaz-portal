{{-- «مشاركاتي»: مشاركات المتقدم في المسابقات بحالتها ورقمها المرجعي --}}
@php
    use App\Support\Dates;
@endphp

<x-layouts.account :title="__('competitions.account.title')">
    @if ($submissions->isEmpty())
        <div class="p-6 concept-panel">
            <x-empty-state icon="trophy" :message="__('competitions.account.empty')" />
            <x-button :href="route('competitions.index')" variant="outline" class="mt-4" arrow>{{ __('competitions.account.browse') }}</x-button>
        </div>
    @else
        <ul class="space-y-4">
            @foreach ($submissions as $submission)
                @if ($submission->competition->student_mode)
                    @include('account.partials.student-competition', ['submission' => $submission])
                    @continue
                @endif
                <li class="acct-row">
                    <x-icon-tile icon="trophy" size="sm" class="shrink-0" />
                    <div class="min-w-0 flex-1 basis-48">
                        <p class="font-bold text-ink">{{ $submission->competition->title }}</p>
                        <p class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted">
                            <span>{{ $submission->status->getLabel() }}</span>
                            @if ($submission->reference)
                                <span>{{ __('competitions.entry.reference') }}: <span dir="ltr" class="font-semibold text-primary">{{ $submission->reference }}</span></span>
                                <span>{{ Dates::display($submission->submitted_at) }}</span>
                            @endif
                        </p>
                    </div>
                    <x-button :href="route('competitions.entry', $submission->competition->slug)" variant="outline" size="sm">{{ __('competitions.account.open') }}</x-button>
                </li>
            @endforeach
        </ul>

        {{ $submissions->links('partials.pagination') }}
    @endif
</x-layouts.account>
