{{--
    عنوان القسم (نمط ٣ في توجيه الدفعة الثانية — plans/2026-10-30-visual-refresh-2-directive.md): شارة صغيرة فوقه اختيارية
    (x-section-eyebrow)، ثم العنوان العريض، وكلمة منه مميزة بنص متدرج اختيارية (highlight: جزء من العنوان نفسه، فلا نص جديد)،
    ثم زخرفة رفيعة بلون التمييز تُرسم عند ظهورها (data-heading-ornament)، ثم سطر وصف رمادي اختياري. وعلى القسم الداكن
    (.section-dark) بألوانه الفاتحة.
--}}
@props(['eyebrow' => null, 'highlight' => null, 'center' => false, 'description' => null])

@php
    // المحتوى مرمَّز من قبل ({{ }} عند المستدعي)، فالمميز يُرمَّز مثله ويُبحث عنه فيه، ويُطبع الناتج بلا ترميز ثانٍ
    $text = trim((string) $slot);
    $mark = filled($highlight) ? e($highlight) : null;
    $parts = $mark !== null && str_contains($text, $mark) ? explode($mark, $text, 2) : null;
@endphp

<div {{ $attributes->class(['text-center' => $center]) }} data-reveal>
    @if (filled($eyebrow))
        <x-section-eyebrow>{{ $eyebrow }}</x-section-eyebrow>
    @endif
    <h2 @class(['text-2xl leading-snug font-bold text-primary-dark sm:text-3xl [.section-dark_&]:text-white', 'mt-3' => filled($eyebrow)])>
        @if ($parts)
            {!! $parts[0] !!}<span class="text-gradient">{!! $mark !!}</span>{!! $parts[1] !!}
        @else
            {!! $text !!}
        @endif
    </h2>
    <svg data-heading-ornament @class(['flourish mt-3', 'mx-auto' => $center]) viewBox="0 0 120 14" fill="none" aria-hidden="true">
        <path d="M2 9c18-7 36-7 54 0s38 7 62-2" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" />
        <circle cx="60" cy="10" r="2.6" fill="currentColor" />
    </svg>
    @if (filled($description))
        <p @class(['mt-3 max-w-2xl leading-8 text-muted [.section-dark_&]:text-white/80', 'mx-auto' => $center])>{{ $description }}</p>
    @endif
</div>
