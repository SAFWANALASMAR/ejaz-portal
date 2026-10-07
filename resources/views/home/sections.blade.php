{{--
    مقاطع الرئيسية بالترتيب (docs/design/ui-reference.md — الرئيسية): البطل، ثم الإحصائيات، ثم الاختصارات.
    تُخزَّن نصاً في App\Support\ContentCache، فلا يدخلها ما يتغير بحالة الدخول أو بالطلب.
    والمقاطع الأخرى (المبادرات، وأحدث الأخبار والبرامج، والشركاء) تُضاف مع بنودها.
--}}
@include('home.partials.hero', ['hero' => $hero])

@if ($stats !== [])
    @include('home.partials.stats', ['stats' => $stats])
@endif

@if ($shortcuts !== [])
    @include('home.partials.shortcuts', ['shortcuts' => $shortcuts])
@endif
