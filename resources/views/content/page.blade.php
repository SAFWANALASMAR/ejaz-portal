{{-- غلاف صفحات المحتوى: التخطيط العام حول المحتوى المخزّن (المتحكم يمرّر HtmlString مهرّباً من Blade) --}}
<x-layouts.app :title="$title" :description="$summary">
    {{ $html }}
</x-layouts.app>
