{{-- مراحل رحلة الاختبار ومرحلة المشارك الحالية (docs/plans/competition-exam.md) --}}
@php
    $steps = ['registration', 'intro', 'instructions', 'questions', 'review'];
    $index = array_search($current, $steps, true);
@endphp

<nav aria-label="{{ __('competitions.exam.step_of', ['current' => $index + 1, 'total' => count($steps)]) }}" class="rounded-2xl border border-line/70 bg-white p-4 shadow-xs">
    <ol class="flex flex-wrap gap-2 text-sm">
        @foreach ($steps as $position => $step)
            <li @class([
                'rounded-full px-3 py-1 font-semibold',
                'bg-primary text-white' => $position === $index,
                'bg-primary-soft text-primary' => $position < $index,
                'bg-surface text-muted' => $position > $index,
            ]) @if ($position === $index) aria-current="step" @endif>
                {{ $position + 1 }}. {{ __('competitions.exam.steps.'.$step) }}
            </li>
        @endforeach
    </ol>
</nav>
