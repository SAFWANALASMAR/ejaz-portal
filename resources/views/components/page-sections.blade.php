{{--
    أقسام الصفحة الثابتة المنشورة من «محرر الصفحات» (EditablePages): المقاطع الظاهرة بترتيبها، تحت رأس الصفحة في قالبها. من ذاكرة
    المحتوى، فيمسحها النشر. ولا شيء قبل نشر الصفحة أول مرة، فيبقى القالب كما هو.
--}}
@php($editable = \App\Support\EditablePages::live(request()->route()?->getName()))

@if (filled($editable['html'] ?? null))
    <div data-page-sections>
        {{ new \Illuminate\Support\HtmlString($editable['html']) }}
    </div>
@endif
