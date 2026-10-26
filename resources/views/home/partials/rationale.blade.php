{{--
    المبررات (الخطة الاستراتيجية ص7 — ملاحظات المدير التنفيذي، 25 أكتوبر 2026): مخطوطة اسم الجمعية بحجم كبير فوق صورة
    المقطع، بملف المخطوطة نفسه الذي في الرأس لا بخط الويب (مراجعة المشرف، 26 أكتوبر 2026)، وبجانبها نص المبررات حرفياً، ثم
    «ولأجل تحقيق هذه الرؤية تأسست:» واسم الجمعية. والمخطوطة زخرفة، فلا يقرؤها قارئ الشاشة واسم الجمعية مكتوب بعدها.
    النصوص والصورة والمخطوطة من اللوحة (App\Support\HomeContent).
--}}
<section class="relative overflow-hidden bg-white py-14 lg:py-20" aria-labelledby="home-rationale">
    <div class="container-page grid items-center gap-10 lg:grid-cols-2 lg:gap-16">
        <div class="relative order-2 lg:order-1" data-reveal>
            <div class="relative overflow-hidden rounded-[2rem] bg-primary-dark shadow-xl">
                <img src="{{ $rationale['image'] }}" alt="{{ $rationale['image_alt'] }}" loading="lazy" class="aspect-[4/3] w-full object-cover opacity-80">
                <span class="absolute inset-0 bg-linear-to-t from-primary-dark via-primary-dark/40 to-transparent" aria-hidden="true"></span>
                <img src="{{ \App\Support\HomeContent::brand('calligraphy_white') }}" alt="" aria-hidden="true" loading="lazy" data-rationale-calligraphy
                    class="absolute inset-x-0 bottom-0 mx-auto w-[88%] px-4 pb-6 drop-shadow-lg sm:pb-8">
            </div>
            <span class="absolute -bottom-4 -start-4 -z-0 h-24 w-24 rounded-3xl bg-secondary-soft" aria-hidden="true"></span>
        </div>

        <div class="order-1 lg:order-2" data-reveal style="--reveal-delay: 120ms">
            <x-section-heading id="home-rationale">{{ $rationale['heading'] }}</x-section-heading>
            <p class="mt-6 text-lg leading-9 text-ink/85 sm:text-xl sm:leading-10">{{ $rationale['text'] }}</p>
            <p class="mt-8 font-semibold text-muted">{{ $rationale['lead'] }}</p>
            <p class="mt-2 text-2xl leading-snug font-bold text-secondary sm:text-3xl">{{ $rationale['outcome'] }}</p>
        </div>
    </div>
</section>
