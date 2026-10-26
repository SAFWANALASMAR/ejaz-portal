{{--
    مقدمة قالب مبني من صفحة محتوى منشورة (Page::INTROS): نص صفحة الموقع السابق أعلى صفحة الخدمة
    أو التواصل أو التقارير، يحرره المحرر من «الصفحات». وغير المنشورة لا يظهر منها شيء.
    ونص فريق البوابة (Page::is_sample) بوسم «مثال تجريبي» كما في /pages/{slug} (الاختبار الشامل 0.14).
--}}
@props(['slug'])

@php
    // نص في ذاكرة المحتوى، فيمسحه أي تعديل في الصفحات، بمفتاح جديد فلا يُقرأ النص المخزَّن قبل الوسم. وغير المنشورة [] لا null، لأن الذاكرة لا تحفظ null
    $intro = \App\Support\ContentCache::remember('pages.intro-tagged.'.$slug, function () use ($slug): array {
        $page = \App\Models\Page::query()->published()->where('slug', $slug)->first();

        return $page === null ? [] : ['sample' => $page->is_sample, 'html' => view('components.content-blocks', ['blocks' => $page->blocks])->render()];
    });
@endphp

@if ($intro !== [])
    @if ($intro['sample'])
        <div class="container-page pt-8">
            <x-sample />
        </div>
    @endif

    {{ new \Illuminate\Support\HtmlString($intro['html']) }}
@endif
