{{-- غلاف صفحات المحتوى: التخطيط العام حول المحتوى المخزّن (المتحكم يمرّر HtmlString مهرّباً من Blade).
     والبيانات المنظمة للعنصر ($schema) ومسار التنقل بأقسامه الأعلى ($breadcrumb) إن مرّرهما (StructuredData). و$documentTitle
     (اختياري) عنوان المتصفح ومحركات البحث إن اختلف عن عنوان الصفحة (عنصر المكتبة بقسمه) --}}
@php
    use App\Support\StructuredData;

    $structured = array_values(array_filter([
        ...($schema ?? []),
        isset($breadcrumb) ? StructuredData::breadcrumbs($title, url()->current(), $breadcrumb) : null,
    ]));
@endphp

<x-layouts.app :title="$documentTitle ?? $title" :description="$summary" :image="$image ?? null" :og-type="$ogType ?? 'website'" :published-time="$publishedTime ?? null">
    @if ($structured !== [])
        @push('head')
            {{ StructuredData::script($structured) }}
        @endpush
    @endif
    {{ $html }}
</x-layouts.app>
