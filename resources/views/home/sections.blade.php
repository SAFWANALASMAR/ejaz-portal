{{--
    مقاطع الرئيسية بترتيب التصور النهائي، النسخة 63 (أمر التصور النهائي §٢ب والمرحلة ب — docs/design/final-concept/Final-Home.dc.html،
    والقرارات في docs/decisions/2026-10-31-visual-refresh-2.md): البطل، ثم البطاقات الخمس، ثم «خدماتنا»، ثم شريط يوتيوب وشريط الكتب
    وبطاقة X (ملاحظة المدير ٢٤: «تحت خدماتنا»)، ثم الإعلانات والأخبار متجاورتين، ثم المقاطع الاختيارية السابقة متى أُظهرت من الإعدادات، ثم «البوابة بالأرقام»،
    ثم «شركاؤنا» قبل التذييل. وكلمتا خادم الحرمين الشريفين ورئيس الجمعية صارتا شريحتين في البطل.
    وكل مقطع يظهر ولو فرغ بحالته الفارغة، والمقطع الاختياري بإعداد إظهاره وحده (قرار صفوان، 11 نوفمبر 2026 —
    docs/decisions/2026-11-11-fix-show-empty-sections.md).
    والخلفيات تتعاقب بين ثلاث درجات خفيفة (data-band 1 و2 و3 — إضافة توجيه المدير، البند ١٥) بعدّ المقاطع الظاهرة وحدها، فلا تتجاور درجة مع نفسها.
    تُخزَّن نصاً في App\Support\ContentCache، فلا يدخلها ما يتغير بحالة الدخول أو بالطلب.
--}}
@php
    $headings = $content['headings'];
    $band = 0;
    $next = function () use (&$band): int {
        return ($band++ % 3) + 1;
    };
@endphp

@include('home.partials.hero', ['hero' => $hero, 'slides' => $slides])

@include('home.partials.identity', ['identity' => $identity, 'texts' => $identity_texts, 'initiatives' => $top_initiatives, 'content' => $content, 'band' => $next()])

@if ($shortcuts !== [])
    @include('home.partials.shortcuts', ['shortcuts' => $shortcuts, 'content' => $content, 'band' => $next()])
@endif

{{-- شريطا يوتيوب والكتب تحت «خدماتنا» مباشرة (ملاحظة المدير ٢٤: «تحت خدماتنا»)، ثم الإعلانات والأخبار --}}
@include('home.partials.strips', ['videos' => $strip_videos, 'books' => $strip_books, 'x' => $x_account, 'headings' => $headings, 'band' => $next()])

@if ($shows['announcements'] || $prize !== null || $shows['news'])
    @include('home.partials.announcements-news', ['announcements' => $announcements, 'news' => $news, 'prize' => $prize, 'tickers' => $content['tickers'], 'showsFeed' => $shows['announcements'] || $prize !== null, 'showsNews' => $shows['news'], 'band' => $next()])
@endif

{{-- المقاطع السابقة الاختيارية: مخفية ما لم تُظهر من «إعدادات الموقع» --}}
@if ($shows['video'])
    @include('home.partials.video', ['youtube' => $video, 'thumbnail' => $video_thumbnail])
@endif

@if ($shows['initiatives'])
    @include('home.partials.initiatives', ['initiatives' => $initiatives])
@endif

@if ($shows['videos'])
    @include('home.partials.videos', ['videos' => $videos])
@endif

{{-- «البوابة بالأرقام» آخراً (البند H9 — docs/decisions/2026-11-01-site-stats-v2.md)، بإعداد إظهاره، وبلا رقم ولا رسم بحالته الفارغة --}}
@if ($shows['stats'])
    @include('home.partials.portal-stats', ['stats' => $stats, 'chart' => $portal_chart, 'title' => $headings['stats'], 'intro' => $headings['stats_intro'], 'band' => $next()])
@endif

@if ($shows['partners'])
    @include('home.partials.partners', ['partners' => $partners, 'sponsors' => $sponsors, 'title' => $headings['partners'], 'band' => $next()])
@endif
