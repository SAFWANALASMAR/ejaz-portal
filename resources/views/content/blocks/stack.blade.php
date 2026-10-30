{{--
    مقاطع النص القصيرة المتتالية (التصور النهائي — Final-About: «الرؤية» و«الرسالة» و«الأهداف» بطاقات متجاورة، و«قيم الجمعية»
    مرقمة): بطاقات بيضاء ترتفع عند المرور، في كل بطاقة رقمها باهتاً (01 و02 و03) والعنوان والنص. والمقاطع من
    ContentBlocks::grouped، فلا نص هنا.
--}}
<section class="mx-auto max-w-[82.5rem] px-4 py-7 sm:px-7">
    <ol @class(['grid gap-5 sm:grid-cols-2', 'lg:grid-cols-3' => count($data['items']) % 3 === 0 || count($data['items']) > 4, 'lg:grid-cols-4' => count($data['items']) === 4]) data-stack data-reveal-group>
        @foreach ($data['items'] as $index => $item)
            <li class="card-lift group relative flex flex-col gap-3 p-6" data-stack-card>
                <span class="absolute end-5 top-4 text-[2.75rem] leading-none font-black text-primary/20" aria-hidden="true">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                <h2 class="text-[1.625rem] font-black text-primary-dark">{{ $item['heading'] }}</h2>
                <div class="prose-ar text-[17px] leading-[1.8] text-[var(--concept-text)]">{{ \App\Support\RichText::toHtml(is_string($item['body'] ?? null) ? $item['body'] : null) }}</div>
            </li>
        @endforeach
    </ol>
</section>
