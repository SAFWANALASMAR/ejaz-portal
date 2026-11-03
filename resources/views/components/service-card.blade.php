{{--
    بطاقة خدمة بالنمط «أ» المعتمد (إضافة توجيه المدير التنفيذي، 3 نوفمبر 2026، البند ٢٩): بطاقة فاتحة بتدرج من #EEF6FD إلى #D3E7F8
    وظل ناعم ونص #1F4565، ومربع أيقونة 64 بكسل بزاوية 18 وحشو 13 بتدرج يتعاقب بين البطاقات، ورسم أبيض ثنائي الطبقة (x-service-icon)،
    ثم الاسم ثم «ادخل ←» في الوسط. مكوّن واحد لصفحة «خدماتنا الإلكترونية» و«خدماتنا» في الرئيسية. والرابط يحمل الاسم.

    href، title، icon (رسم من التسعة)، fallback (أيقونة الموقع لما لا رسم له)، external (تبويب جديد)، والوصف في الـ slot.
--}}
@props(['href', 'title', 'icon', 'fallback' => 'squares-2x2', 'external' => false])

<a href="{{ $href }}"@if ($external) target="_blank" rel="noopener noreferrer"@else @navigate($href)@endif {{ $attributes->class(['service-card group']) }}>
    <span class="service-card-tile"><x-service-icon :name="$icon" :fallback="$fallback" /></span>
    <span class="service-card-name">{{ $title }}</span>
    @if (trim((string) $slot) !== '')
        <span class="service-card-text">{{ $slot }}</span>
    @endif
    @if ($external)
        <span class="sr-only">({{ __('site.opens_in_new_tab') }})</span>
    @endif
    <span class="service-card-go" aria-hidden="true">{{ __('ui.services.enter_arrow') }}</span>
</a>
