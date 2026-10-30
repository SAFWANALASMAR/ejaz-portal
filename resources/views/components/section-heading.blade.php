{{--
    عنوان القسم (التصور النهائي — «خدماتنا» و«قيم الجمعية» و«كيف تسجّل؟»): العنوان العريض بين خطين رفيعين ينتهيان بمعيّن
    (data-heading-ornament)، وتحته سطر وصف اختياري. وفي جهة البداية بلا خطين (center=false). وشارة صغيرة فوقه اختيارية
    (x-section-eyebrow). والكلمة المميزة اختيارية (highlight: جزء من العنوان نفسه، فلا نص جديد). وعلى القسم الداكن بالأبيض.
--}}
@props(['eyebrow' => null, 'highlight' => null, 'center' => false, 'description' => null, 'size' => 'lg'])

@php
    // المحتوى مرمَّز من قبل ({{ }} عند المستدعي)، فالمميز يُرمَّز مثله ويُبحث عنه فيه، ويُطبع الناتج بلا ترميز ثانٍ
    $text = trim((string) $slot);
    $mark = filled($highlight) ? e($highlight) : null;
    $parts = $mark !== null && str_contains($text, $mark) ? explode($mark, $text, 2) : null;
    $sizes = ['lg' => 'text-[1.875rem] sm:text-[2.5rem]', 'md' => 'text-2xl sm:text-[1.875rem]'];
@endphp

<div {{ $attributes->class(['flex flex-col gap-1.5', 'items-center text-center' => $center]) }} data-reveal>
    @if (filled($eyebrow))
        <x-section-eyebrow class="mb-1">{{ $eyebrow }}</x-section-eyebrow>
    @endif
    <div class="flex items-center gap-3.5">
        @if ($center)
            <span class="heading-flank hidden sm:flex" data-heading-ornament aria-hidden="true"></span>
        @endif
        <h2 @class(['font-black leading-tight text-primary-dark [.section-dark_&]:text-white', $sizes[$size] ?? $sizes['lg']])>
            @if ($parts)
                {!! $parts[0] !!}<span class="text-gradient">{!! $mark !!}</span>{!! $parts[1] !!}
            @else
                {!! $text !!}
            @endif
        </h2>
        @if ($center)
            <span class="heading-flank heading-flank-end hidden sm:flex" aria-hidden="true"></span>
        @endif
    </div>
    @if (filled($description))
        <p class="text-lg text-[var(--concept-text)] sm:text-xl [.section-dark_&]:text-white/80">{{ $description }}</p>
    @endif
</div>
