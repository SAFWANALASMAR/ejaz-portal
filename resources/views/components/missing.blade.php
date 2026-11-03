{{--
    علامة محتوى ناقص من الجمعية. تظهر في التطوير والبيئة التجريبية فقط،
    ولا يرى زائر الموقع في الإنتاج شيئاً مكانها، ولا زائر رابط العرض بـ HIDE_MISSING_MARKERS (MissingText::visible). انظر docs/open-questions.md
--}}
@props(['what'])

@if (\App\Support\MissingText::visible())
    <span {{ $attributes->merge(['class' => 'keep-color inline-block rounded border border-dashed border-accent bg-surface px-2 py-1 text-sm text-accent-dark']) }}>
        【{{ __('site.missing') }}: {{ $what }}】
    </span>
@endif
