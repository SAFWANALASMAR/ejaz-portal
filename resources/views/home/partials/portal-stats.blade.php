{{--
    «البوابة بالأرقام» (أمر التصور النهائي، النسخة 63 — البند H9): مكانها آخر الرئيسية قبل «شركاؤنا». محتواها الكامل (الزوار وقراءات
    الكتب ومشاهدات المقاطع والمسجلون في المسابقة، ورسم آخر 30 يوماً) في فرعها feature/site-stats-v2. وإلى أن يصل: الأرقام المعتمدة من
    «إعدادات الموقع» وحدها (BuildHomePage::stats)، بطاقات ملونة يعدّ رقمها تصاعدياً (x-stat-badge)، ولا يظهر القسم بدونها.

    $stats: list<array{label, value, suffix, icon}>، و$title: عنوان القسم من «محتوى الرئيسية».
--}}
<section class="home-band" data-band="{{ $band }}" aria-labelledby="home-stats" data-portal-stats>
    <div class="mx-auto max-w-[82.5rem] px-4 pt-6 pb-7 sm:px-7 lg:pt-7 lg:pb-8">
        <x-section-heading id="home-stats" icon="chart-bar" class="mb-4">{{ $title }}</x-section-heading>

        <ul class="grid grid-cols-2 gap-3.5 lg:grid-cols-4" data-reveal-group>
            @foreach ($stats as $stat)
                <li class="tone-card px-4 py-5">
                    <x-stat-badge :value="$stat['value']" :label="$stat['label']" :suffix="$stat['suffix']" :icon="$stat['icon']" />
                </li>
            @endforeach
        </ul>
    </div>
</section>
