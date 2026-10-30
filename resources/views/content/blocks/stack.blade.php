{{--
    بطاقات مرقمة متراكمة (درس الموقع القديم: «من نحن» و«الرؤية» و«الرسالة» — plans/visual-refresh.md): كل مقطع نص قصير بطاقة
    برقم كبير باهت (01 و02 و03) وتدرج هادئ من الهوية، تلتصق تحت الرأس فتتراكم مع التمرير (position: sticky). ولمن طلب تقليل
    الحركة بطاقات متتالية بلا تراكم (visual.css). المقاطع من ContentBlocks::grouped.
--}}
@php($tones = ['blue', 'violet', 'green', 'navy', 'sky'])

<section class="mesh-light py-12 sm:py-16">
    <div class="container-page">
        <ol class="stack-cards mx-auto max-w-4xl" data-stack>
            @foreach ($data['items'] as $index => $item)
                <li class="stack-card" data-stack-card data-tone="{{ $tones[$index % count($tones)] }}" style="--stack-index: {{ $index }}">
                    <span class="stack-number" aria-hidden="true">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                    <h2 class="text-2xl font-bold text-primary-dark sm:text-3xl">{{ $item['heading'] }}</h2>
                    <svg class="flourish mt-3" viewBox="0 0 120 14" fill="none" aria-hidden="true">
                        <path d="M2 9c18-7 36-7 54 0s38 7 62-2" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" />
                    </svg>
                    <div class="prose-ar mt-4 text-lg">{{ \App\Support\RichText::toHtml(is_string($item['body'] ?? null) ? $item['body'] : null) }}</div>
                </li>
            @endforeach
        </ol>
    </div>
</section>
