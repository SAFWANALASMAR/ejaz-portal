{{--
    مقدمة قالب مبني من صفحة محتوى منشورة (Page::INTROS): نص صفحة الموقع السابق أعلى صفحة الخدمة
    أو التواصل أو التقارير، يحرره المحرر من «الصفحات». وغير المنشورة لا يظهر منها شيء.
    ووسم نص فريق البوابة (Page::is_sample) في اللوحة وحدها لا للعامة (قرار صفوان، 29 أكتوبر 2026).
    وexcept: نص ظاهر في الصفحة نفسها (رأس «تواصل معنا» — البند ٣٠): إن كان نص المقدمة كله فيه لم تُعرض مرة ثانية، ونص آخر يظهر كما كان
    (مراجعة المشرف على #157).
--}}
@props(['slug', 'except' => null])

@php
    // نص في ذاكرة المحتوى، فيمسحه أي تعديل في الصفحات، بمفتاح جديد فلا يُقرأ النص المخزَّن قبل الوسم. وغير المنشورة [] لا null، لأن الذاكرة لا تحفظ null
    $intro = \App\Support\ContentCache::remember('pages.intro-tagged.'.$slug, function () use ($slug): array {
        $page = \App\Models\Page::query()->published()->where('slug', $slug)->first();

        return $page === null ? [] : ['html' => view('components.content-blocks', ['blocks' => $page->blocks])->render()];
    });
@endphp

@php
    $plain = fn (string $text): string => trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    $repeated = $intro !== [] && filled($except) && ($text = $plain($intro['html'])) !== '' && str_contains($plain((string) $except), $text);
@endphp

@if ($intro !== [] && ! $repeated)
    {{ new \Illuminate\Support\HtmlString($intro['html']) }}
@endif
