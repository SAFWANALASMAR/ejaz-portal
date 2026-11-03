{{--
    شارة رقم (نمط ٥): رقم كبير بلون التمييز يعدّ تصاعدياً حين يظهر (data-count-to — motion.js)، ووصفه تحته. الرقم مكتوب كاملاً
    في الصفحة، فيبقى صحيحاً بلا سكربت ولمن طلب تقليل الحركة. والأرقام من اللوحة وحدها (التصحيحات ٢ في ui-reference.md).
    onLoad: يعدّ عند التحميل لا عند الظهور (عدّاد ظاهر في الشاشة الأولى).
--}}
@props(['value', 'label', 'suffix' => '', 'icon' => null, 'onLoad' => false])

<div data-stat-badge {{ $attributes->class(['flex flex-col items-center gap-2 text-center']) }}>
    @if ($icon)
        <x-icon-tile :icon="$icon" variant="glass" />
    @endif
    <span class="order-2 text-sm leading-6 text-white/85">{{ $label }}</span>
    {{-- العدّ يبدّل النص الظاهر فيقرؤه قارئ الشاشة بأرقام عابرة: الظاهر مخفي عنه، والرقم النهائي له ثابت --}}
    <span class="stat-number order-1 text-4xl font-black tabular-nums sm:text-5xl" aria-hidden="true"
        data-count-to="{{ (int) $value }}" data-count-suffix="{{ $suffix }}" @if ($onLoad) data-count-on-load @endif>{{ number_format((int) $value) }}{{ $suffix }}</span>
    <span class="sr-only">{{ number_format((int) $value) }}{{ $suffix }}</span>
</div>
