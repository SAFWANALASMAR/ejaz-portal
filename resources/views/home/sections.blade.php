{{--
    مقاطع الرئيسية ببنية التصور النهائي (صفوان، 30 أكتوبر 2026، 18:36 — docs/design/final-concept/Final-Home.dc.html، والقرار ١٣
    في docs/decisions/2026-10-30-visual-refresh-2.md): البطل، ثم الرؤية والرسالة والقيم وجوائز البراهين، ثم «خدماتنا»، ثم
    الإعلانات والمستجدات والأخبار. ثم مقاطع الخطة الاستراتيجية بترتيب 25 أكتوبر (كلمة خادم الحرمين الشريفين، وكلمة رئيس مجلس
    الإدارة، والمبررات)، ثم المقاطع التي تُظهر من الإعدادات، وقنوات التواصل.
    تُخزَّن نصاً في App\Support\ContentCache، فلا يدخلها ما يتغير بحالة الدخول أو بالطلب.
--}}
@include('home.partials.hero', ['hero' => $hero, 'slides' => $slides])
@include('home.partials.identity', ['identity' => $identity, 'initiatives' => $top_initiatives])
@include('home.partials.shortcuts', ['shortcuts' => $shortcuts, 'content' => $content])
@include('home.partials.announcements-news', ['announcements' => $announcements, 'news' => $news, 'prize' => $prize, 'tickers' => $content['tickers']])
@include('home.partials.strips', ['videos' => $strip_videos, 'books' => $strip_books, 'x' => $x_account])

@include('home.partials.king', ['king' => $content['king']])
@include('home.partials.chairman', ['chairman' => $content['chairman']])
@include('home.partials.rationale', ['rationale' => $content['rationale']])

@if ($stats !== [])
    @include('home.partials.stats', ['stats' => $stats])
@endif

@if ($video !== null)
    @include('home.partials.video', ['youtube' => $video, 'thumbnail' => $video_thumbnail])
@endif

@if ($initiatives->isNotEmpty())
    @include('home.partials.initiatives', ['initiatives' => $initiatives])
@endif

@if ($videos->isNotEmpty())
    @include('home.partials.videos', ['videos' => $videos])
@endif

@if ($partners->isNotEmpty() || $sponsors->isNotEmpty())
    @include('home.partials.partners', ['partners' => $partners, 'sponsors' => $sponsors])
@endif

@if ($accounts !== [])
    @include('home.partials.accounts', ['accounts' => $accounts, 'channels' => $content['channels']])
@endif
