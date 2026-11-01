{{--
    كلمة خادم الحرمين الشريفين (الخطة الاستراتيجية ص3): خرجت من الرئيسية بالنسخة 63 (شريحة في البطل)، وتُعرض في «عن الجمعية»
    (المرحلة ج) بطاقةً ملونة فاتحة: الكلمة والتوقيع في جهة البداية، والصورة في إطار في جهة النهاية، وعلى الجوال النص ثم الصورة.
    النص والصورة من «محتوى الرئيسية» (App\Support\HomeContent)، والإطار موسوم «صورة معتمدة من الجمعية» ما لم تُرفع صورته.
--}}
<section class="mx-auto max-w-[82.5rem] px-4 pt-8 pb-4 sm:px-7" aria-labelledby="about-king">
    <div class="tone-card grid items-center gap-6 p-6 sm:p-8 lg:grid-cols-[1.4fr_1fr]" data-tone="light" data-reveal>
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

        <div class="relative mx-auto w-full max-w-xs">
            <img src="{{ $king['image'] }}" alt="{{ $king['image_alt'] }}" loading="lazy" decoding="async" width="320" height="400"
                class="aspect-[4/5] w-full rounded-[1.5rem] bg-white/40 object-cover object-top shadow-[0_20px_40px_-24px_rgba(20,46,69,.6)]">
            @unless (\App\Support\HomeContent::hasStoredImage('king'))
                <x-missing :what="__('ui.inner.image_slot_king')" class="absolute inset-x-3 bottom-3 text-center" />
            @endunless
        </div>
    </div>
</section>
