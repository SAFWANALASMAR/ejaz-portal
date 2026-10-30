{{--
    عنوان القسم (التحسين البصري — docs/plans/visual-refresh.md): عبارة صغيرة فوقه اختيارية (eyebrow)، ثم العنوان بالأزرق
    العريض، وكلمة منه مميزة بنص متدرج اختيارية (highlight: جزء من العنوان نفسه، فلا نص جديد)، ثم خط زخرفي بلون التمييز
    يُرسم عند ظهوره. وعلى القسم الداكن (.section-dark) بألوانه الفاتحة.
--}}
@props(['eyebrow' => null, 'highlight' => null, 'center' => false])

@php
    // المحتوى مرمَّز من قبل ({{ }} عند المستدعي)، فالمميز يُرمَّز مثله ويُبحث عنه فيه، ويُطبع الناتج بلا ترميز ثانٍ
    $text = trim((string) $slot);
    $mark = filled($highlight) ? e($highlight) : null;
    $parts = $mark !== null && str_contains($text, $mark) ? explode($mark, $text, 2) : null;
@endphp

<div {{ $attributes->class(['text-center' => $center]) }} data-reveal>
    @if (filled($eyebrow))
        <span class="eyebrow">{{ $eyebrow }}</span>
    @endif
    <h2 @class(['text-2xl leading-snug font-bold text-primary sm:text-3xl [.section-dark_&]:text-white', 'mt-3' => filled($eyebrow)])>
        @if ($parts)
            {!! $parts[0] !!}<span class="text-gradient">{!! $mark !!}</span>{!! $parts[1] !!}
        @else
            {!! $text !!}
        @endif
    </h2>
    <svg @class(['flourish mt-3', 'mx-auto' => $center]) viewBox="0 0 120 12" fill="none" aria-hidden="true">
        <path d="M2 8c18-6 36-6 54 0s38 6 62-2" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
    </svg>
</div>
