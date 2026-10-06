{{--
    شعار نصي مؤقت: نجمة هندسية عامة واسم الجمعية.
    يُستبدل بالشعار الرسمي بصيغة SVG عند استلامه — docs/open-questions.md
--}}
<a href="{{ route('home') }}" class="flex items-center gap-3" aria-label="{{ __('site.name') }}">
    <svg class="size-10 shrink-0 text-gold" viewBox="0 0 48 48" fill="none" aria-hidden="true">
        <path d="M24 3l5.6 7.5 9.2-1.3-1.3 9.2L45 24l-7.5 5.6 1.3 9.2-9.2-1.3L24 45l-5.6-7.5-9.2 1.3 1.3-9.2L3 24l7.5-5.6-1.3-9.2 9.2 1.3z" stroke="currentColor" stroke-width="2.2" stroke-linejoin="round"/>
        <circle cx="24" cy="24" r="7" fill="#1F4F4B"/>
    </svg>
    <span class="leading-tight">
        <span class="block text-lg font-bold text-primary">{{ __('site.short_name') }}</span>
        <span class="hidden text-xs text-muted sm:block">{{ __('site.name') }}</span>
    </span>
</a>
