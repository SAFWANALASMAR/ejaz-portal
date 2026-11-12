{{--
    الشريط المتحرك (أمر التصور النهائي، النسخة 63 — المرحلة أ، البند ٨): CSS وحده، أفقي (شريط الخدمات والشركاء ويوتيوب وأغلفة الكتب)
    أو عمودي يصعد (شريط الأخبار). نسختان متتاليتان من المحتوى تتحركان بمقدار نسخة فيستمر بلا قفزة، والثانية مخفية عن قارئ الشاشة
    (aria-hidden) وروابطها خارج ترتيب لوحة المفاتيح (tabindex=-1 — home.js)، وتبقى تُضغط بالفأرة واللمس: كان inert يجعل نصف ما يظهر
    من الشريط لا يفتح شيئاً (ملاحظة المدير التنفيذي على الإطلاق التجريبي، 12 نوفمبر 2026 — docs/decisions/2026-11-12-fix-marquee-copy-clickable.md). ويتوقف بالمرور والتركيز، وبزر الإيقاف (data-ticker-toggle — home.js، WCAG 2.2.2)، وخارج
    الشاشة (data-loop — motion.js). ولمن طلب تقليل الحركة لا يتحرك: صف يُمرَّر باليد، أو عمود بارتفاعه يُمرَّر.

    id: معرّف الشريط (لزر الإيقاف). label: اسمه لقارئ الشاشة. direction: horizontal أو vertical. duration: مدة الدورة بالثواني.
    height: ارتفاع العمودي (CSS). controls: زر الإيقاف (افتراضاً ظاهر). والعناصر في الـ slot بلا معرّفات (تتكرر في النسخة الثانية).
    static: صف ثابت في الوسط بلا حركة ولا نسخة ثانية ولا زر إيقاف.
    repeat: لعناصر قليلة لا تملأ العرض: المحتوى يتكرر في المسار ضعف هذا العدد (الأولى ظاهرة والبقية مخفية عن قارئ الشاشة ولوحة
    المفاتيح)، والحركة بمقدار نصف المسار، فيملأ العرض ويستمر بلا فراغ ولا قفزة (إضافة توجيه المدير، 3 نوفمبر 2026، البند ١١:
    «ما كان يتحرك يبقى يتحرك» — شريطا المكتبة والشركاء بعناصر قليلة على رابط العرض).
--}}
@props(['id', 'label', 'direction' => 'horizontal', 'duration' => 40, 'height' => '20rem', 'controls' => true, 'static' => false, 'repeat' => 1])

@php
    $direction = $direction === 'vertical' ? 'vertical' : 'horizontal';
    $style = '--marquee-duration: '.max(5, (int) $duration).'s;'.($direction === 'vertical' ? ' --marquee-height: '.e($height).';' : '');
@endphp

<div {{ $attributes->class(['marquee-wrap relative']) }}>
    {{-- زر الإيقاف قبل الشريط في ترتيب التركيز، كما يظهر في زاويته العليا: يصل إليه مستخدم لوحة المفاتيح قبل أن يمر على كل
         البطاقات، وباسم شريطه لأنه خارج منطقته (فحص الوصول 0.15). وموضعه مطلق، فلا يتغير شكل الشريط. ولا زر للصف الثابت --}}
    @if ($controls && ! $static)
        <button type="button" data-ticker-toggle="{{ $id }}" aria-pressed="false" aria-controls="{{ $id }}"
            data-label-pause="{{ __('ui.marquee.pause_named', ['name' => $label]) }}" data-label-play="{{ __('ui.marquee.play_named', ['name' => $label]) }}" aria-label="{{ __('ui.marquee.pause_named', ['name' => $label]) }}"
            class="marquee-toggle">
            {{ \App\Support\Icon::svg('pause', 'size-4', ['aria-hidden' => 'true', 'data-icon-pause' => true]) }}
            {{ \App\Support\Icon::svg('play', 'size-4', ['aria-hidden' => 'true', 'data-icon-play' => true, 'hidden' => true]) }}
        </button>
    @endif

    @if ($static)
        <div id="{{ $id }}" data-marquee-static data-direction="{{ $direction }}" role="region" aria-label="{{ $label }}" class="marquee">
            <div class="marquee-track">
                <div class="marquee-group">{{ $slot }}</div>
            </div>
        </div>
    @else
        <div id="{{ $id }}" data-marquee data-direction="{{ $direction }}" data-loop role="region" aria-label="{{ $label }}" class="marquee" style="{{ $style }}">
            <div class="marquee-track">
                <div class="marquee-group">{{ $slot }}</div>
                @for ($copy = 1; $copy < 2 * max(1, (int) $repeat); $copy++)
                    <div class="marquee-group" data-marquee-copy aria-hidden="true">{{ $slot }}</div>
                @endfor
            </div>
        </div>
    @endif
</div>
