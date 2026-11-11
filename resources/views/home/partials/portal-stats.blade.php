{{--
    «البوابة بالأرقام» (أمر التصور النهائي، النسخة 63 — البند H9، docs/decisions/2026-11-01-site-stats-v2.md): آخر الرئيسية قبل «شركاؤنا»،
    بتخطيط Final-Home: بطاقات الأرقام في جهة، ورسم الزيارات في آخر 30 يوماً في الأخرى. الأرقام الآلية (الزيارات والمسجلون في
    المسابقات، ثم صفحات المكتبة المقروءة ودقائق المشاهدة والاستماع — docs/decisions/2026-11-02-portal-stats-library.md) من المخزّن في الخلفية (HomePortalStats)، ثم المعتمدة من «إعدادات الموقع»، بطاقات ملونة يعدّ رقمها تصاعدياً
    (x-stat-badge — motion.js، ولا عدّ لمن طلب تقليل الحركة فيظهر الرقم النهائي). والرسم SVG مضمَّن بالأزرق وحده، ويُرسم خطه
    حين يظهر. وبلا رقم ولا رسم (قبل أول حساب) بحالته الفارغة (قرار صفوان، 11 نوفمبر 2026).

    $stats: list<array{label, value, suffix, icon}>، و$chart: array{line, area, total, max}|null، و$title و$intro من «محتوى الرئيسية».
--}}
<section class="home-band" data-band="{{ $band }}" aria-labelledby="home-stats" data-portal-stats>
    <div class="mx-auto max-w-[82.5rem] px-4 pt-6 pb-7 sm:px-7 lg:pt-7 lg:pb-8">
        <x-section-heading id="home-stats" icon="chart-bar" :description="$intro" class="mb-4">{{ $title }}</x-section-heading>

        @if ($stats === [] && $chart === null)
            <x-empty-state icon="chart-bar" :message="__('home.empty.portal_stats')" data-home-empty="portal_stats" />
        @endif
        <div @class(['grid gap-4', 'lg:grid-cols-[1fr_1.05fr]' => $stats !== [] && $chart !== null])>
            @if ($stats !== [])
                <ul @class(['grid grid-cols-2 gap-3.5', 'lg:grid-cols-4' => $chart === null && count($stats) <= 4, 'lg:grid-cols-3' => $chart === null && count($stats) > 4]) data-reveal-group>
                    @foreach ($stats as $stat)
                        <li class="tone-card px-4 py-5">
                            <x-stat-badge :value="$stat['value']" :label="$stat['label']" :suffix="$stat['suffix']" :icon="$stat['icon']" />
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($chart !== null)
                <figure class="tone-card flex min-w-0 flex-col gap-2 px-5 pt-5 pb-3.5" data-tone="dark" data-portal-chart data-reveal>
                    <figcaption class="text-lg font-black sm:text-xl">{{ __('home.portal_stats.chart_title') }}</figcaption>
                    <svg viewBox="0 0 1000 220" preserveAspectRatio="none" class="block h-44 w-full sm:h-52" role="img"
                        aria-label="{{ __('home.portal_stats.chart_label', ['days' => \App\Support\HomePortalStats::DAYS, 'total' => number_format($chart['total']), 'max' => number_format($chart['max'])]) }}">
                        <defs>
                            <linearGradient id="portal-stats-area" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0" stop-color="var(--color-brand-light)" stop-opacity=".55" />
                                <stop offset="1" stop-color="var(--color-brand-light)" stop-opacity="0" />
                            </linearGradient>
                        </defs>
                        <polygon points="{{ $chart['area'] }}" fill="url(#portal-stats-area)" />
                        <polyline class="portal-chart-line" points="{{ $chart['line'] }}" pathLength="100" fill="none" stroke="var(--color-white)" stroke-width="3.5" vector-effect="non-scaling-stroke"
                            stroke-linejoin="round" stroke-linecap="round" />
                    </svg>
                    <span class="tone-card-muted flex justify-between text-xs sm:text-sm" aria-hidden="true">
                        <span>{{ __('home.portal_stats.chart_start') }}</span>
                        <span>{{ __('home.portal_stats.chart_end') }}</span>
                    </span>
                </figure>
            @endif
        </div>
    </div>
</section>
