{{--
    قيم الجمعية الأربع (الخطة الاستراتيجية ص9 و10 — ملاحظات المدير التنفيذي، 25 أكتوبر 2026): دائرة بصورة كل قيمة، ثم
    عنوانها ووصفها حرفياً، تظهر تباعاً عند التمرير (data-reveal بتأخير متدرج، وبلا حركة لمن طلب تقليلها). والصور زخرفة،
    فالعنوان يكفي. النصوص والصور من اللوحة (App\Support\HomeContent).
--}}
<section class="section-tint relative overflow-hidden py-14 lg:py-20" aria-labelledby="home-values">
    <div class="bg-pattern pointer-events-none absolute inset-0 opacity-60" aria-hidden="true"></div>

    <div class="container-page relative">
        <div class="mx-auto max-w-xl text-center">
            <span class="mx-auto block h-1 w-12 rounded-full bg-accent" aria-hidden="true"></span>
            <h2 id="home-values" class="mt-3 text-2xl font-bold text-primary sm:text-3xl">{{ $values['heading'] }}</h2>
        </div>

        <ol class="mt-12 grid gap-10 sm:grid-cols-2 lg:grid-cols-4 lg:gap-8">
            @foreach ($values['items'] as $index => $value)
                <li class="flex flex-col items-center text-center" data-reveal style="--reveal-delay: {{ $index * 150 }}ms">
                    <span class="relative block size-40 sm:size-44">
                        <span class="absolute inset-0 rounded-full bg-linear-to-br from-brand via-accent to-secondary p-1" aria-hidden="true"></span>
                        <img src="{{ $value['image'] }}" alt="" loading="lazy"
                            class="relative size-full rounded-full border-[6px] border-white object-cover shadow-lg">
                        <span class="absolute -bottom-1 start-1/2 inline-flex size-10 -translate-x-1/2 items-center justify-center rounded-full bg-accent text-lg font-bold text-white ring-4 ring-surface rtl:translate-x-1/2" aria-hidden="true">{{ $index + 1 }}</span>
                    </span>
                    <h3 class="mt-6 text-xl font-bold text-primary-dark">{{ $value['title'] }}</h3>
                    <p class="mt-2 max-w-xs leading-8 text-muted">{{ $value['text'] }}</p>
                </li>
            @endforeach
        </ol>
    </div>
</section>
