{{--
    علامة محتوى ناقص من الجمعية. تظهر في التطوير والبيئة التجريبية فقط،
    ولا يرى زائر الموقع في الإنتاج شيئاً مكانها. انظر docs/open-questions.md
--}}
@props(['what'])

@unless (app()->isProduction())
    <span {{ $attributes->merge(['class' => 'inline-block rounded border border-dashed border-gold bg-sand px-2 py-1 text-sm text-gold-dark']) }}>
        【{{ __('site.missing') }}: {{ $what }}】
    </span>
@endunless
