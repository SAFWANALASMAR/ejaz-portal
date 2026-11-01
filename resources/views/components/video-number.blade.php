{{-- رقم بطاقة المقطع (01، 02…) في زاوية الصورة كبطاقات الموقع السابق المرقّمة (تدقيق المشرف). زخرفي، فالترتيب ظاهر بالقائمة نفسها --}}
@props(['number'])

<span data-video-number aria-hidden="true"
    class="pointer-events-none absolute start-3 top-2 z-[1] text-4xl leading-none font-black text-white/85 tabular-nums [text-shadow:0_2px_12px_rgb(0_0_0/0.45)]">{{ str_pad((string) $number, 2, '0', STR_PAD_LEFT) }}</span>
