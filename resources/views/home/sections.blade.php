{{--
    مقاطع الرئيسية بترتيب التصور النهائي، النسخة 63 (أمر التصور النهائي §٢ب والمرحلة ب — docs/design/final-concept/Final-Home.dc.html،
    والقرارات في docs/decisions/2026-10-31-visual-refresh-2.md): البطل، ثم البطاقات الخمس، ثم «خدماتنا»، ثم الإعلانات والأخبار
    متجاورتين، ثم شريط يوتيوب وشريط الكتب وبطاقة X، ثم المقاطع الاختيارية السابقة متى أُظهرت من الإعدادات، ثم «البوابة بالأرقام»،
    ثم «شركاؤنا» قبل التذييل. وكلمتا خادم الحرمين الشريفين ورئيس الجمعية صارتا شريحتين في البطل.
    والخلفيات تتعاقب بين درجتين (data-band 1 و2) بعدّ المقاطع الظاهرة وحدها، فلا تتجاور درجة مع نفسها.
    تُخزَّن نصاً في App\Support\ContentCache، فلا يدخلها ما يتغير بحالة الدخول أو بالطلب.
--}}
@php
    $headings = $content['headings'];
    $band = 0;
    $next = function () use (&$band): int {
        return ($band++ % 2) + 1;
    };
@endphp

@include('home.partials.hero', ['hero' => $hero, 'slides' => $slides])

@include('home.partials.identity', ['identity' => $identity, 'initiatives' => $top_initiatives, 'content' => $content, 'band' => $next()])

@if ($shortcuts !== [])
    @include('home.partials.shortcuts', ['shortcuts' => $shortcuts, 'content' => $content, 'band' => $next()])
@endif

@if ($announcements->isNotEmpty() || $prize !== null || $news->isNotEmpty())
    @include('home.partials.announcements-news', ['announcements' => $announcements, 'news' => $news, 'prize' => $prize, 'tickers' => $content['tickers'], 'band' => $next()])
@endif

@if ($strip_videos->isNotEmpty() || $strip_books->isNotEmpty() || $x_account !== null)
    @include('home.partials.strips', ['videos' => $strip_videos, 'books' => $strip_books, 'x' => $x_account, 'headings' => $headings, 'band' => $next()])
@endif

{{-- المقاطع السابقة الاختيارية: مخفية ما لم تُظهر من «إعدادات الموقع» --}}
@if ($video !== null)
    @include('home.partials.video', ['youtube' => $video, 'thumbnail' => $video_thumbnail])
@endif

@if ($initiatives->isNotEmpty())
    @include('home.partials.initiatives', ['initiatives' => $initiatives])
@endif

@if ($videos->isNotEmpty())
    @include('home.partials.videos', ['videos' => $videos])
@endif

{{-- «البوابة بالأرقام» آخراً (البند H9): مكانها هنا، ومحتواها في فرع feature/site-stats-v2. ولا يظهر فارغاً --}}
@if ($stats !== [])
    @include('home.partials.portal-stats', ['stats' => $stats, 'title' => $headings['stats'], 'band' => $next()])
@endif

@if ($partners->isNotEmpty() || $sponsors->isNotEmpty())
    @include('home.partials.partners', ['partners' => $partners, 'sponsors' => $sponsors, 'title' => $headings['partners'], 'band' => $next()])
@endif
