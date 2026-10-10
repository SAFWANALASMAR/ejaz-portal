{{--
    شعار نصي مؤقت: رمز هندسي عام، واسم الجمعية وتحته بقية الاسم بخط صغير.
    يُستبدل بالشعار الرسمي بصيغة SVG عند استلامه — docs/open-questions.md
--}}
@props(['light' => false])

<a href="{{ route('home') }}" class="flex items-center gap-3" aria-label="{{ __('site.name') }}">
    <svg class="size-11 shrink-0" viewBox="0 0 48 48" fill="none" aria-hidden="true">
        <path d="M24 3l5.6 7.5 9.2-1.3-1.3 9.2L45 24l-7.5 5.6 1.3 9.2-9.2-1.3L24 45l-5.6-7.5-9.2 1.3 1.3-9.2L3 24l7.5-5.6-1.3-9.2 9.2 1.3z" stroke="#B8955A" stroke-width="2.2" stroke-linejoin="round"/>
        <circle cx="24" cy="24" r="7.5" fill="{{ $light ? '#F5F1EA' : '#1F4F4B' }}"/>
    </svg>
    <span class="leading-tight whitespace-nowrap">
        <span @class(['block text-xl font-bold', 'text-white' => $light, 'text-primary' => ! $light])>{{ __('site.short_name') }}</span>
        <span @class(['block text-[11px] font-medium', 'text-white/70' => $light, 'text-muted' => ! $light])>{{ __('site.name_line') }}</span>
    </span>
</a>
