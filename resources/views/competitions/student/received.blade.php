{{-- بعد التسليم: «تم استلام إجاباتك» فقط، بلا درجة ولا إجابة صحيحة (قرار المدير التنفيذي). والنتيجة في «مشاركاتي» بعد نشرها --}}
@php
    use App\Support\Dates;
@endphp

<x-layouts.account :title="__('competitions.student.round_title', ['round' => $round->name, 'competition' => $competition->title])">
    <section class="space-y-5 rounded-2xl border border-line/70 bg-white p-6 text-center shadow-xs">
        <span class="mx-auto block w-fit text-primary">{{ svg('heroicon-o-check-circle', 'size-14') }}</span>
        <p role="status" class="text-xl leading-9 font-bold text-primary">{{ __('competitions.student.received') }}</p>
        <p class="text-muted">{{ __('competitions.student.results_later') }}</p>
        <p class="text-sm text-muted">{{ __('competitions.entry.submitted_at') }}: <span class="font-medium text-ink">{{ Dates::display($attempt->submitted_at, withTime: true) }}</span></p>
        <x-button :href="route('account.competitions')" variant="outline" arrow>{{ __('competitions.account.title') }}</x-button>
    </section>
</x-layouts.account>
