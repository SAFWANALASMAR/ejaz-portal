{{-- غلاف صفحات المحتوى: التخطيط العام حول المحتوى المخزّن (المتحكم يمرّر HtmlString مهرّباً من Blade).
     والبيانات المنظمة للعنصر ($schema) ومسار التنقل بأقسامه الأعلى ($breadcrumb) إن مرّرهما (StructuredData) --}}
<x-layouts.app :title="$title" :description="$summary" :image="$image ?? null">
    @php
        $structured = array_values(array_filter([
            ...($schema ?? []),
            isset($breadcrumb) ? \App\Support\StructuredData::breadcrumbs($title, url()->current(), $breadcrumb) : null,
        ]));
    @endphp
    @if ($structured !== [])
        @push('head')
            {{ \App\Support\StructuredData::script($structured) }}
        @endpush
    @endif
    {{ $html }}
</x-layouts.app>
