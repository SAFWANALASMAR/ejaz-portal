{{-- صفحة محتوى عامة: رأس القسم، ثم مقاطعها (تُخزَّن نصاً — PageController). ووسم نص فريق البوابة (Page::is_sample) في
     اللوحة وحدها لا للعامة (قرار صفوان، 29 أكتوبر 2026) --}}
<x-page-hero :title="$page->title" :summary="$page->summary" />

<x-content-blocks :blocks="$page->blocks" />
