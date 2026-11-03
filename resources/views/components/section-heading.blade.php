{{--
    عنوان القسم الموحد (أمر التصور النهائي، النسخة 63 — البندان G4 وN2): مربع الأيقونة (x-icon-tile) بجانب العنوان، بمقاس واحد
    ومستوى واحد (h2) في كل الصفحات: 24 بكسل على الجوال (البند R12) و32 على الحاسوب. وفي المنتصف بين خطين ينتهيان بمعيّن
    (center، data-heading-ornament)، وإلا في جهة البداية. وتحته سطر وصف اختياري، وفوقه شارة صغيرة اختيارية (x-section-eyebrow).
    والكلمة المميزة اختيارية (highlight: جزء من العنوان نفسه، فلا نص جديد). وعلى القسم الداكن بالأبيض.
    icon: أيقونة القسم (App\Support\Icon)، وإلا أيقونة عامة. وsize باقٍ في الخصائص ولا يغيّر المقاس (مستوى واحد).
--}}
@props(['eyebrow' => null, 'highlight' => null, 'center' => false, 'description' => null, 'size' => 'lg', 'icon' => 'sparkles'])

@php
    // المحتوى مرمَّز من قبل ({{ }} عند المستدعي): يُفك ترميزه إلى النص نفسه، ثم يُطبع بـ {{ }} فيُرمَّز مرة واحدة كما
    // كان، بلا طباعة خام (docs/security.md). والمميز يُبحث عنه في النص المفكوك
    $text = htmlspecialchars_decode(trim((string) $slot), ENT_QUOTES);
    $mark = filled($highlight) ? (string) $highlight : null;
    $parts = $mark !== null && str_contains($text, $mark) ? explode($mark, $text, 2) : null;
@endphp

<div {{ $attributes->class(['section-heading flex flex-col gap-1.5', 'items-center text-center' => $center]) }} data-section-heading data-reveal>
    @if (filled($eyebrow))
        <x-section-eyebrow class="mb-1">{{ $eyebrow }}</x-section-eyebrow>
    @endif
    <div class="flex items-center gap-3.5">
        @if ($center)
            <span class="heading-flank hidden sm:flex" data-heading-ornament aria-hidden="true"></span>
        @endif
        <h2 class="section-heading-title flex items-center gap-2.5 font-black leading-tight text-primary-dark [.section-dark_&]:text-white">
            <x-icon-tile :icon="$icon" shade="medium" size="sm" class="section-heading-icon" data-loop />
            <span>
                @if ($parts)
                    {{ $parts[0] }}<span class="text-gradient">{{ $mark }}</span>{{ $parts[1] }}
                @else
                    {{ $text }}
                @endif
            </span>
        </h2>
        @if ($center)
            <span class="heading-flank heading-flank-end hidden sm:flex" aria-hidden="true"></span>
        @endif
    </div>
    @if (filled($description))
        <p class="text-lg text-[var(--concept-text)] sm:text-xl [.section-dark_&]:text-white/80">{{ $description }}</p>
    @endif
</div>
