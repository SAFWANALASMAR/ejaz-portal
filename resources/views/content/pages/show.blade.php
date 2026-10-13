{{-- صفحة محتوى عامة: رأس القسم، ثم مقاطعها (تُخزَّن نصاً — PageController) --}}
<x-page-hero :title="$page->title" :summary="$page->summary" />

<x-content-blocks :blocks="$page->blocks" />
