{{--
    كلمة خادم الحرمين الشريفين (الخطة الاستراتيجية ص3): خرجت من الرئيسية بالنسخة 63 (شريحة في البطل)، وتُعرض في «عن الجمعية»
    (المرحلة ج) بطاقةً ناعمة بالكلمة والتوقيع بعرضها كله، بلا صورة: الصورة في شريحة البطل وحدها (إضافة توجيه المدير، البند ٢٤).
    النص والصورة من «محتوى الرئيسية» (App\Support\HomeContent)، والإطار موسوم «صورة معتمدة من الجمعية» ما لم تُرفع صورته.
--}}
<section class="mx-auto max-w-[82.5rem] px-4 pt-8 pb-4 sm:px-7" aria-labelledby="about-king">
    <div class="tone-card p-6 sm:p-8" data-tone="soft" data-reveal>
        <div class="flex flex-col gap-4">
            <h2 id="about-king" class="tone-chip">
                {{ \App\Support\Icon::svg('chat-bubble-bottom-center-text', 'size-4', ['aria-hidden' => 'true']) }}
                {{ $king['heading'] }}
            </h2>
            <figure>
                <blockquote class="relative ps-8 text-2xl leading-[1.9] font-bold text-balance sm:text-[1.75rem]">
                    <span class="absolute -top-3 start-0 text-6xl leading-none opacity-40" aria-hidden="true">”</span>
                    <p>{{ $king['text'] }}</p>
                </blockquote>
                <figcaption class="mt-5 border-s-4 border-current ps-4">
                    <span class="tone-card-muted block text-base font-semibold">{{ $king['signature_title'] }}</span>
                    <span class="mt-1 block text-xl font-black">{{ $king['signature_name'] }}</span>
                </figcaption>
            </figure>
        </div>

    </div>
</section>
