{{--
    كلمة خادم الحرمين الشريفين (الخطة الاستراتيجية ص3 — ملاحظات المدير التنفيذي، 25 أكتوبر 2026): شريط مستطيل بعرض
    الصفحة، النص في جهة البداية (اليمين) والصورة في جهة النهاية (اليسار). وعلى الجوال: النص ثم الصورة.
    النص والصورة من اللوحة (App\Support\HomeContent).
--}}
<section class="relative overflow-hidden bg-linear-to-b from-primary-soft to-white" aria-labelledby="home-king">
    <img src="{{ asset('images/decor/pattern-corner.svg') }}" alt="" aria-hidden="true" class="pointer-events-none absolute -top-6 start-0 w-56 opacity-60 sm:w-72">

    <div class="container-page relative grid items-end gap-8 pt-10 sm:pt-14 lg:grid-cols-[1.25fr_1fr] lg:gap-12 lg:pt-16">
        <div class="pb-6 lg:pb-16" data-reveal>
            <h2 id="home-king" class="inline-flex items-center gap-2 rounded-full bg-white/80 px-4 py-1.5 text-sm font-semibold text-accent-dark shadow-xs ring-1 ring-line">
                {{ \App\Support\Icon::svg('chat-bubble-bottom-center-text', 'size-4', ['aria-hidden' => 'true']) }}
                {{ $king['heading'] }}
            </h2>

            <figure class="mt-6">
                <blockquote class="relative ps-8 text-2xl leading-[1.9] font-bold text-balance text-primary-dark sm:text-3xl sm:leading-[1.8]">
                    <span class="absolute -top-3 start-0 text-6xl leading-none text-brand/40" aria-hidden="true">”</span>
                    <p>{{ $king['text'] }}</p>
                </blockquote>
                <figcaption class="mt-8 border-s-4 border-accent ps-4">
                    <span class="block text-base font-semibold text-accent-dark">{{ $king['signature_title'] }}</span>
                    <span class="mt-1 block text-xl font-bold text-ink">{{ $king['signature_name'] }}</span>
                </figcaption>
            </figure>
        </div>

        <div class="relative mx-auto mb-10 w-full max-w-xs sm:max-w-sm lg:mb-16">
            <span class="absolute -inset-3 rotate-3 rounded-[2.5rem] bg-linear-to-br from-brand via-primary to-secondary opacity-90" aria-hidden="true"></span>
            <img src="{{ $king['image'] }}" alt="{{ $king['image_alt'] }}" fetchpriority="high"
                class="relative aspect-[4/5] w-full rounded-[2.5rem] bg-white object-cover object-top shadow-xl">
        </div>
    </div>

    <div class="h-2 bg-linear-to-l from-accent via-brand to-secondary" aria-hidden="true"></div>
</section>
