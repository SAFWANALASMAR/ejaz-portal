{{--
    مقدمة قالب مبني من صفحة محتوى منشورة (Page::INTROS): نص صفحة الموقع السابق أعلى صفحة الخدمة
    أو التواصل أو التقارير، يحرره المحرر من «الصفحات». وغير المنشورة لا يظهر منها شيء.
--}}
@props(['slug'])

@php
    // نص في ذاكرة المحتوى، فيمسحه أي تعديل في الصفحات. والفارغ '' لا null، لأن الذاكرة لا تحفظ null
    $intro = \App\Support\ContentCache::remember('pages.intro.'.$slug, function () use ($slug): string {
        $page = \App\Models\Page::query()->published()->where('slug', $slug)->first();

        return $page === null ? '' : view('components.content-blocks', ['blocks' => $page->blocks])->render();
    });
@endphp

{{ new \Illuminate\Support\HtmlString($intro) }}
