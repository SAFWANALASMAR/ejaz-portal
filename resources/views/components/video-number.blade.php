{{-- رقم بطاقة المقطع (01، 02…) في زاوية الصورة كبطاقات الموقع السابق المرقّمة (تدقيق المشرف). زخرفي، فالترتيب ظاهر بالقائمة نفسها.
     أبيض خالص كله (إضافة توجيه المدير، 3 نوفمبر 2026، البند ١٩) بظل خفيف يُقرأ به فوق الصورة الفاتحة --}}
@props(['number'])

<span data-video-number aria-hidden="true"
    class="pointer-events-none absolute start-3 top-2 z-[1] text-4xl leading-none font-black text-white tabular-nums [text-shadow:0_1px_6px_rgb(15_37_54/0.55)]">{{ str_pad((string) $number, 2, '0', STR_PAD_LEFT) }}</span>
