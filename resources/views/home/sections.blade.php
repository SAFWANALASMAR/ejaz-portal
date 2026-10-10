{{--
    مقاطع الرئيسية بالترتيب (docs/design/ui-reference.md — الرئيسية): البطل، ثم الإحصائيات، ثم الاختصارات.
    تُخزَّن نصاً في App\Support\ContentCache، فلا يدخلها ما يتغير بحالة الدخول أو بالطلب.
    ثم المبادرات (البند ٣)، ثم أحدث الأخبار والشركاء (البند ٦). والبرامج تُضاف مع وحدتها في المرحلة 3.
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

@if ($partners->isNotEmpty())
    @include('home.partials.partners', ['partners' => $partners])
@endif
