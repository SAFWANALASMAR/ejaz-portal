{{-- سادسًا: تأكيد إنهاء الاختبار بالنص الحرفي. ولا إجابة صحيحة ولا درجة --}}
@php
    use App\Support\Dates;
@endphp

<x-layouts.account :title="__('competitions.exam.title', ['competition' => $competition->title])">
    <section class="space-y-5 rounded-2xl border border-line/70 bg-white p-6 text-center shadow-xs">
        <span class="mx-auto block w-fit text-primary">{{ svg('heroicon-o-check-circle', 'size-14') }}</span>
        <p role="status" class="text-xl leading-9 font-bold text-primary">{{ __('competitions.exam.finished') }}</p>
        <dl class="grid gap-4 text-start sm:grid-cols-2">
            <div>
                <dt class="text-sm text-muted">{{ __('competitions.entry.reference') }}</dt>
                <dd class="mt-1 text-2xl font-bold tracking-widest text-primary" dir="ltr">{{ $submission->reference }}</dd>
            </div>
            <div>
                <dt class="text-sm text-muted">{{ __('competitions.entry.submitted_at') }}</dt>
                <dd class="mt-1 font-medium text-ink">{{ Dates::display($submission->submitted_at, withTime: true) }}</dd>
            </div>
        </dl>
    </section>
</x-layouts.account>
