{{-- ما يحتاج تصرف المستخدم: طلب طُلب استكماله، أو مسودة لم تُرسل وفرصتها مفتوحة --}}
@if ($summary['needs_completion']->isNotEmpty() || $summary['open_drafts']->isNotEmpty())
    <section aria-labelledby="attention" class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-amber-900">
        <h2 id="attention" class="flex items-center gap-2 font-semibold">
            {{ svg('heroicon-o-exclamation-triangle', 'size-5') }}
            {{ __('account.summary.attention') }}
        </h2>
        <ul class="mt-3 space-y-2">
            @foreach ($summary['needs_completion'] as $request)
                <li class="flex flex-wrap items-center justify-between gap-2">
                    <span>{{ __('account.summary.needs_completion', ['opening' => $request->opening->title, 'reference' => $request->reference]) }}</span>
                    <x-button size="sm" :href="route('account.requests.show', $request)">{{ __('account.summary.complete') }}</x-button>
                </li>
            @endforeach
            @foreach ($summary['open_drafts'] as $request)
                <li class="flex flex-wrap items-center justify-between gap-2">
                    <span>{{ __('account.summary.open_draft', ['opening' => $request->opening->title]) }}</span>
                    <x-button size="sm" variant="outline" :href="route('account.requests.show', $request)">{{ __('account.summary.complete') }}</x-button>
                </li>
            @endforeach
        </ul>
    </section>
@endif
