{{--
    مقاطع الرئيسية بترتيب المدير التنفيذي (ملاحظاته عبر صفوان، 25 أكتوبر 2026 — App\Actions\Content\BuildHomePage):
    كلمة خادم الحرمين الشريفين، ثم كلمة رئيس مجلس الإدارة، ثم المبررات، ثم القيم الأربع، ثم شريط الأخبار، ثم شريط
    الإعلانات، ثم قنوات التواصل. والمقاطع السابقة لا تظهر إلا إن أُظهرت من الإعدادات، قبل قنوات التواصل.
    تُخزَّن نصاً في App\Support\ContentCache، فلا يدخلها ما يتغير بحالة الدخول أو بالطلب.
--}}
@if ($hero)
    @include('home.partials.hero', ['hero' => $hero])
@else
    <h1 class="sr-only">{{ __('site.name') }}</h1>
@endif

@include('home.partials.king', ['king' => $content['king']])
@include('home.partials.chairman', ['chairman' => $content['chairman']])
@include('home.partials.rationale', ['rationale' => $content['rationale']])
@include('home.partials.values', ['values' => $content['values']])

@if ($news->isNotEmpty())
    @include('home.partials.news-ticker', ['news' => $news, 'tickers' => $content['tickers']])
@endif

@if ($announcements->isNotEmpty())
    @include('home.partials.announcements', ['announcements' => $announcements, 'tickers' => $content['tickers']])
@endif

@if ($stats !== [])
    @include('home.partials.stats', ['stats' => $stats])
@endif

@if ($shortcuts !== [])
    @include('home.partials.shortcuts', ['shortcuts' => $shortcuts])
@endif

@if ($video !== null)
    @include('home.partials.video', ['youtube' => $video])
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
