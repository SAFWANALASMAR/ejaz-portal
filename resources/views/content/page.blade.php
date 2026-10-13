{{-- غلاف صفحات المحتوى: التخطيط العام حول المحتوى المخزّن (المتحكم يمرّر HtmlString مهرّباً من Blade) --}}
<x-layouts.app :title="$title" :description="$summary" :image="$image ?? null">
    {{ $html }}
</x-layouts.app>
