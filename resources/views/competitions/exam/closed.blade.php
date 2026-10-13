{{-- الاختبار مغلق أو لم يُفتح بعد، والمشاركة لم تُرسل --}}
<x-layouts.account :title="__('competitions.exam.title', ['competition' => $competition->title])">
    <x-alert type="warning">{{ __('competitions.exam.closed') }}</x-alert>
</x-layouts.account>
