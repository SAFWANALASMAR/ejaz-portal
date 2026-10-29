{{--
    مقدمة قالب مبني من صفحة محتوى منشورة (Page::INTROS): نص صفحة الموقع السابق أعلى صفحة الخدمة
    أو التواصل أو التقارير، يحرره المحرر من «الصفحات». وغير المنشورة لا يظهر منها شيء.
    ووسم نص فريق البوابة (Page::is_sample) في اللوحة وحدها لا للعامة (قرار صفوان، 29 أكتوبر 2026).
--}}
@props(['slug'])

@php
    // نص في ذاكرة المحتوى، فيمسحه أي تعديل في الصفحات، بمفتاح جديد فلا يُقرأ النص المخزَّن قبل الوسم. وغير المنشورة [] لا null، لأن الذاكرة لا تحفظ null
    $intro = \App\Support\ContentCache::remember('pages.intro-tagged.'.$slug, function () use ($slug): array {
        $page = \App\Models\Page::query()->published()->where('slug', $slug)->first();

        return $page === null ? [] : ['html' => view('components.content-blocks', ['blocks' => $page->blocks])->render()];
    });
@endphp

@if ($intro !== [])
    {{ new \Illuminate\Support\HtmlString($intro['html']) }}
@endif
