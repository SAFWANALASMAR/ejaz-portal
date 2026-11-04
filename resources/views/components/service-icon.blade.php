{{--
    رسوم «خدماتنا الإلكترونية» بالنمط «أ» (إضافة توجيه المدير التنفيذي، 3 نوفمبر 2026، البند ٢٩): رسم أبيض ثنائي الطبقة، خط بلون النص
    وطبقة تعبئة بشفافية 0.3 (.d). رسوم أصلية في مربع 48×48، زخرفة (aria-hidden)، والاسم في رابط البطاقة. وما لا رسم له من هذه
    التسعة أيقونته من مجموعة الموقع (Phosphor duotone — App\Support\Icon). والرسوم وسوم Blade مكتوبة هنا لا نصاً يُطبع خاماً
    (RawOutputTest)، وخطها من الصنف .service-icon-line في concept.css.
--}}
@props(['name', 'fallback' => 'squares-2x2'])

@php($drawn = in_array($name, ['programs', 'competitions', 'initiatives', 'inquiries', 'membership', 'jobs', 'volunteering', 'store', 'surveys'], true))

@if ($drawn)
    <svg viewBox="0 0 48 48" {{ $attributes->class(['service-icon']) }} aria-hidden="true" focusable="false" data-service-icon="{{ $name }}">
        @switch($name)
            @case('programs')
                <path class="d" d="M6 18 24 9l18 9-18 9z"/><g class="service-icon-line"><path d="M6 18 24 9l18 9-18 9z"/><path d="M13 22v9c3 3 7 4.5 11 4.5S32 34 35 31v-9"/><path d="M42 18v11"/><circle cx="42" cy="31" r="1.5" fill="currentColor"/></g>
                @break
            @case('competitions')
                <path class="d" d="M15 7h18v10a9 9 0 0 1-18 0z"/><g class="service-icon-line"><path d="M15 7h18v10a9 9 0 0 1-18 0z"/><path d="M33 10h6v3a6 6 0 0 1-6 6M15 10H9v3a6 6 0 0 0 6 6"/><path d="M24 26v7M17 41h14M19 41l1.5-8h7L29 41"/><path d="m24 11 1.4 2.8 3 .4-2.2 2.1.5 3L24 17.8l-2.7 1.5.5-3-2.2-2.1 3-.4z" stroke-width="1.6"/></g>
                @break
            @case('initiatives')
                <path class="d" d="M11 8h24l-5 8 5 8H11z"/><g class="service-icon-line"><path d="M11 42V7"/><path d="M11 8h24l-5 8 5 8H11"/><path d="M6 42h12"/></g>
                @break
            @case('inquiries')
                <path class="d" d="M6 9h24v15H15l-6 5v-5H6z"/><g class="service-icon-line"><path d="M6 9h24v15H15l-6 5v-5H6z"/><path d="M34 17h8v15h-3v5l-6-5H20v-4"/><path d="M14 15h8M14 19h5"/></g>
                @break
            @case('membership')
                <circle class="d" cx="24" cy="16" r="7"/><g class="service-icon-line"><circle cx="24" cy="16" r="7"/><path d="M11 40c1.5-7 7-11 13-11s11.5 4 13 11"/><circle cx="10" cy="20" r="4.5"/><circle cx="38" cy="20" r="4.5"/><path d="M3 36c.8-4 3.4-6.5 7-6.5M45 36c-.8-4-3.4-6.5-7-6.5"/></g>
                @break
            @case('jobs')
                <rect class="d" x="6" y="15" width="36" height="25" rx="4"/><g class="service-icon-line"><rect x="6" y="15" width="36" height="25" rx="4"/><path d="M17 15v-4a3 3 0 0 1 3-3h8a3 3 0 0 1 3 3v4"/><path d="M6 25c11 5 25 5 36 0"/><path d="M24 26v4"/></g>
                @break
            @case('volunteering')
                <path class="d" d="M24 25s-10-6-10-12a5 5 0 0 1 10-2 5 5 0 0 1 10 2c0 6-10 12-10 12z"/><g class="service-icon-line"><path d="M24 25s-10-6-10-12a5 5 0 0 1 10-2 5 5 0 0 1 10 2c0 6-10 12-10 12z"/><path d="M5 33h7l8 4h9a3 3 0 0 0 0-6h-6"/><path d="M12 33v8H5"/><path d="M29 37l9-5a3 3 0 0 1 4 4l-11 7H20"/></g>
                @break
            @case('store')
                <path class="d" d="M9 16h30l-2 24H11z"/><g class="service-icon-line"><path d="M9 16h30l-2 24H11z"/><path d="M17 20v-6a7 7 0 0 1 14 0v6"/><path d="M19 30h10"/></g>
                @break
            @case('surveys')
                <circle class="d" cx="24" cy="24" r="17"/><g class="service-icon-line"><circle cx="24" cy="24" r="17"/><path d="M17 28c2 3.5 4.3 5 7 5s5-1.5 7-5"/><path d="M18 19v1M30 19v1" stroke-width="3.4"/></g><g fill="currentColor"><path d="m39 6 1 2.2 2.4.3-1.8 1.6.5 2.4L39 11.3 36.9 12.5l.5-2.4-1.8-1.6 2.4-.3z"/></g>
                @break
        @endswitch
    </svg>
@else
    {{ \App\Support\Icon::svg($fallback, 'service-icon', ['aria-hidden' => 'true']) }}
@endif
