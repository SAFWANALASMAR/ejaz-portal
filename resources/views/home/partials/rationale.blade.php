{{--
    المبررات (الخطة الاستراتيجية ص7): خرجت من الرئيسية بالنسخة 63، وتُعرض في «عن الجمعية» (المرحلة ج). مخطوطة اسم الجمعية بملف
    المخطوطة نفسه الذي في الرأس فوق صورة المقطع (مراجعة المشرف، 26 أكتوبر 2026)، وبجانبها نص المبررات حرفياً، ثم «ولأجل تحقيق هذه
    الرؤية تأسست:» واسم الجمعية. والمخطوطة زخرفة، فلا يقرؤها قارئ الشاشة واسم الجمعية مكتوب بعدها. النصوص والصورة والمخطوطة من
    اللوحة (App\Support\HomeContent).
--}}
<section class="band-sky mt-6 py-10 lg:py-12" aria-labelledby="about-rationale">
    <div class="mx-auto grid max-w-[82.5rem] items-center gap-8 px-4 sm:px-7 lg:grid-cols-2 lg:gap-12">
        <div class="relative order-2 overflow-hidden rounded-[1.5rem] bg-primary shadow-[0_20px_40px_-24px_rgba(20,46,69,.6)] lg:order-1" data-reveal>
            <img src="{{ $rationale['image'] }}" alt="{{ $rationale['image_alt'] }}" loading="lazy" width="768" height="576" class="aspect-[4/3] w-full object-cover opacity-80">
            <span class="absolute inset-0 bg-linear-to-t from-primary via-primary/40 to-transparent" aria-hidden="true"></span>
            <img src="{{ \App\Support\HomeContent::brand('calligraphy_white') }}" alt="" aria-hidden="true" loading="lazy" width="1219" height="185" data-rationale-calligraphy
                class="absolute inset-x-0 bottom-0 mx-auto w-[88%] px-4 pb-6 drop-shadow-lg sm:pb-8">
        </div>

        <div class="order-1 lg:order-2" data-reveal style="--reveal-delay: 120ms">
            <x-section-heading id="about-rationale" icon="light-bulb">{{ $rationale['heading'] }}</x-section-heading>
            <p class="mt-5 text-lg leading-9 text-[var(--concept-text)] sm:text-xl sm:leading-10">{{ $rationale['text'] }}</p>
            <p class="mt-6 font-semibold text-muted">{{ $rationale['lead'] }}</p>
            <p class="mt-2 text-2xl leading-snug font-black text-primary-dark sm:text-3xl">{{ $rationale['outcome'] }}</p>
        </div>
    </div>
</section>
