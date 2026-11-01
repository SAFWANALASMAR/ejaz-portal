{{-- ما يحتاج تصرف المستخدم: طلب طُلب استكماله، أو مسودة لم تُرسل وفرصتها مفتوحة --}}
@if ($summary['needs_completion']->isNotEmpty() || $summary['open_drafts']->isNotEmpty())
    {{-- بلغة التصور النهائي (11): صندوق سماوي بحد، ومربع أيقونة متدرج (ملاحظة المدير 19:06) --}}
    <section aria-labelledby="attention" class="rounded-[1.125rem] border border-[var(--concept-pale)] bg-primary-soft p-5 text-ink">
        <h2 id="attention" class="flex items-center gap-3 text-[19px] font-black text-primary-dark">
            <x-icon-tile icon="exclamation-triangle" shade="dark" size="sm" />
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
