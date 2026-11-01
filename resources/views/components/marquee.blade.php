{{--
    الشريط المتحرك (أمر التصور النهائي، النسخة 63 — المرحلة أ، البند ٨): CSS وحده، أفقي (شريط الخدمات والشركاء ويوتيوب وأغلفة الكتب)
    أو عمودي يصعد (شريط الأخبار). نسختان متتاليتان من المحتوى تتحركان بمقدار نسخة فيستمر بلا قفزة، والثانية مخفية عن قارئ الشاشة
    ولوحة المفاتيح (aria-hidden وinert). ويتوقف بالمرور والتركيز، وبزر الإيقاف (data-ticker-toggle — home.js، WCAG 2.2.2)، وخارج
    الشاشة (data-loop — motion.js). ولمن طلب تقليل الحركة لا يتحرك: صف يُمرَّر باليد، أو عمود بارتفاعه يُمرَّر.

    id: معرّف الشريط (لزر الإيقاف). label: اسمه لقارئ الشاشة. direction: horizontal أو vertical. duration: مدة الدورة بالثواني.
    height: ارتفاع العمودي (CSS). controls: زر الإيقاف (افتراضاً ظاهر). والعناصر في الـ slot بلا معرّفات (تتكرر في النسخة الثانية).
--}}
@props(['id', 'label', 'direction' => 'horizontal', 'duration' => 40, 'height' => '20rem', 'controls' => true])

@php
    $direction = $direction === 'vertical' ? 'vertical' : 'horizontal';
    $style = '--marquee-duration: '.max(5, (int) $duration).'s;'.($direction === 'vertical' ? ' --marquee-height: '.e($height).';' : '');
@endphp

<div {{ $attributes->class(['marquee-wrap relative']) }}>
    <div id="{{ $id }}" data-marquee data-direction="{{ $direction }}" data-loop role="region" aria-label="{{ $label }}" class="marquee" style="{{ $style }}">
        <div class="marquee-track">
            <div class="marquee-group">{{ $slot }}</div>
            <div class="marquee-group" data-marquee-copy aria-hidden="true" inert>{{ $slot }}</div>
        </div>
    </div>

    @if ($controls)
        <button type="button" data-ticker-toggle="{{ $id }}" aria-pressed="false" aria-controls="{{ $id }}"
            data-label-pause="{{ __('ui.marquee.pause') }}" data-label-play="{{ __('ui.marquee.play') }}" aria-label="{{ __('ui.marquee.pause') }}"
            class="marquee-toggle">
            {{ \App\Support\Icon::svg('pause', 'size-4', ['aria-hidden' => 'true', 'data-icon-pause' => true]) }}
            {{ \App\Support\Icon::svg('play', 'size-4', ['aria-hidden' => 'true', 'data-icon-play' => true, 'hidden' => true]) }}
        </button>
    @endif
</div>
