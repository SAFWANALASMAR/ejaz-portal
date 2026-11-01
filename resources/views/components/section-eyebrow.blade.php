{{--
    الشارة الصغيرة فوق عنوان القسم (نمط ٣ في توجيه الدفعة الثانية، ودرس الموقع القديم «✦ اسم القسم»): كبسولة بعلامة زخرفية
    ونص من أسماء الأقسام القائمة. وعلى القسم الداكن بألوانه الفاتحة (visual.css).
--}}
<span data-section-eyebrow {{ $attributes->class(['eyebrow']) }}>
    <span class="eyebrow-mark" aria-hidden="true">✦</span>
    {{ $slot }}
</span>
