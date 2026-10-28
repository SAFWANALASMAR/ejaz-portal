{{-- صفحة محتوى عامة: رأس القسم، ثم وسم «مثال تجريبي» إن كان نصها من فريق البوابة (Page::is_sample)، ثم مقاطعها
     (تُخزَّن نصاً — PageController) --}}
<x-page-hero :title="$page->title" :summary="$page->summary" />

@if ($page->is_sample)
    <div class="container-page pt-8">
        <x-sample />
    </div>
@endif

<x-content-blocks :blocks="$page->blocks" />
