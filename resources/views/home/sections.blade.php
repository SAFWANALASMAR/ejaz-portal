{{--
    مقاطع الرئيسية بالترتيب (docs/design/ui-reference.md — الرئيسية): البطل، ثم الإحصائيات، ثم الاختصارات.
    تُخزَّن نصاً في App\Support\ContentCache، فلا يدخلها ما يتغير بحالة الدخول أو بالطلب.
    ثم المبادرات، ثم أحدث الأخبار، ثم نماذج الفيديو والحسابات الرسمية والشركاء والرعاة (ملاحظات المدير التنفيذي).
--}}
@include('home.partials.hero', ['hero' => $hero])

@if ($stats !== [])
    @include('home.partials.stats', ['stats' => $stats])
@endif

@if ($shortcuts !== [])
    @include('home.partials.shortcuts', ['shortcuts' => $shortcuts])
@endif

@if ($initiatives->isNotEmpty())
    @include('home.partials.initiatives', ['initiatives' => $initiatives])
@endif

@if ($news->isNotEmpty())
    @include('home.partials.news', ['news' => $news])
@endif

@if ($videos->isNotEmpty())
    @include('home.partials.videos', ['videos' => $videos])
@endif

@if ($accounts !== [])
    @include('home.partials.accounts', ['accounts' => $accounts])
@endif

@if ($partners->isNotEmpty() || $sponsors->isNotEmpty())
    @include('home.partials.partners', ['partners' => $partners, 'sponsors' => $sponsors])
@endif
