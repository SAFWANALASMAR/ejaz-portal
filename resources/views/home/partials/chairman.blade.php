{{--
    كلمة رئيس مجلس الإدارة (الخطة الاستراتيجية ص4 — البند P4): خرجت من الرئيسية بالنسخة 63 (شريحة في البطل)، وتُعرض في «عن الجمعية»
    (المرحلة ج، Final-About) بطاقةً ملونة داكنة: إطار صورة الرئيس (موسوم «صورة معتمدة من الجمعية» ما لم تُرفع من اللوحة)، ثم شارة
    العنوان، والمقتطف (الافتتاح ثم أول فقرة فعلية — قرار صفوان، 26 أكتوبر 2026)، والاسم الموحد (البند N4) والصفة، و«اقرأ الكلمة
    كاملة» تنفتح في مكانها. النصوص والصورة من «محتوى الرئيسية» (App\Support\HomeContent).
--}}
<section class="mx-auto max-w-[82.5rem] px-4 pt-6 pb-4 sm:px-7" aria-labelledby="about-chairman">
    <div class="tone-card flex flex-col gap-7 p-6 sm:p-8 md:flex-row md:items-center" data-tone="dark" data-chairman-card data-reveal>
        <div class="relative shrink-0 md:w-[13.75rem]" data-image-slot="chairman">
            <img src="{{ $chairman['image'] }}" alt="{{ $chairman['image_alt'] }}" loading="lazy" width="220" height="260"
                class="h-64 w-full rounded-[1.25rem] bg-white/15 object-cover object-top shadow-[0_20px_40px_-24px_rgba(0,0,0,.6)]">
            @unless (\App\Support\HomeContent::hasStoredImage('chairman'))
                <x-missing :what="__('ui.inner.image_slot_chairman')" class="absolute inset-x-2 bottom-2 text-center" />
            @endunless
        </div>
        <div class="flex min-w-0 flex-col gap-3">
            <h2 id="about-chairman" class="tone-chip">
                {{ \App\Support\Icon::svg('chat-bubble-left-right', 'size-4', ['aria-hidden' => 'true']) }}
                {{ $chairman['heading'] }}
            </h2>
            <div class="space-y-2 text-xl leading-[1.9]">
                @foreach (\App\Support\HomeContent::paragraphs($chairman['excerpt']) as $paragraph)
                    <p>{{ $paragraph }}</p>
                @endforeach
            </div>
            <p class="flex flex-col border-s-[3px] border-current ps-3">
                <span class="text-[19px] font-black" data-chairman-name>{{ $chairman['name'] }}</span>
                <span class="tone-card-muted text-[15px]">{{ $chairman['title'] }}</span>
            </p>
            <details class="group">
                <summary class="tone-pill cursor-pointer list-none self-start [&::-webkit-details-marker]:hidden">
                    {{ __('home.sections.chairman.read_more') }}
                    {{ \App\Support\Icon::svg('arrow-left', 'size-4', ['aria-hidden' => 'true']) }}
                </summary>
                <div class="mt-5 space-y-4 text-lg leading-8">
                    @foreach (\App\Support\HomeContent::paragraphs($chairman['full']) as $paragraph)
                        <p>{{ $paragraph }}</p>
                    @endforeach
                </div>
            </details>
        </div>
    </div>
</section>
