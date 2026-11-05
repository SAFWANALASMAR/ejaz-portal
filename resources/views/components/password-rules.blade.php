{{--
    شروط كلمة المرور تحت حقلها، تُعلَّم عند تحققها أثناء الكتابة، ومؤشر قوتها نصاً يُعلَن لقارئ الشاشة (password-rules.js).
    والقواعد نفسها في الخادم (PasswordPolicy)، فهذا عون للكتابة لا تحقق. وفحص البيانات الشخصية من حقول الاسم والبريد
    والجوال في الصفحة نفسها إن وُجدت (التسجيل و«بياناتي»)، وما لا يُعرف في المتصفح يفحصه الخادم.
--}}
@props(['for' => 'field-password', 'compact' => false])

@php
    $rules = ['length', 'letter', 'mixed', 'number', 'symbol', 'personal_item'];
@endphp

{{-- compact (صفحة التسجيل على الحاسوب، فتدخل بطاقتها كاملة في 1366×768 — المدير عبر المشرف، 5 نوفمبر 2026): الشروط متجاورة بخط أصغر،
     والمؤشر ونص القوة في سطر واحد، وتنبيه التسريب لقارئ الشاشة ويظهر متى كان التركيز في عمود كلمة المرور (concept.css). النصوص كلها
     كما هي، فلا ينقص قارئَ الشاشة شيء. وعلى الجوال كما هي --}}
<div data-password-rules data-for="{{ $for }}" @if ($compact) data-compact @endif
    data-met="{{ __('account.password_rules.met') }}" data-unmet="{{ __('account.password_rules.unmet') }}"
    data-strength-label="{{ __('account.password_rules.strength') }}"
    @foreach (['empty', 'weak', 'fair', 'strong'] as $level) data-level-{{ $level }}="{{ __('account.password_rules.levels.'.$level) }}" @endforeach
    {{ $attributes->merge(['class' => 'rounded-xl border border-line bg-surface p-4 text-sm'.($compact ? ' lg:px-3 lg:py-2 lg:text-xs' : '')]) }}>
    <p class="font-semibold text-ink" id="{{ $for }}-rules-heading">{{ __('account.password_rules.heading') }}</p>

    <ul @class(['mt-2 space-y-1.5', 'lg:mt-1 lg:flex lg:flex-wrap lg:gap-x-3 lg:gap-y-0.5 lg:space-y-0' => $compact]) aria-labelledby="{{ $for }}-rules-heading">
        @foreach ($rules as $rule)
            <li data-rule="{{ $rule }}" data-state="unmet" @class(['flex items-start gap-2 text-muted data-[state=met]:text-primary', 'lg:items-center lg:gap-1' => $compact])>
                <span aria-hidden="true" @class(['mt-0.5 inline-flex size-4 shrink-0 items-center justify-center rounded-full border border-current text-[10px] leading-none', 'lg:mt-0 lg:size-3 lg:text-[8px]' => $compact]) data-mark>•</span>
                <span>{{ __('account.password_rules.'.$rule) }}</span>
                <span class="sr-only" data-state-text>{{ __('account.password_rules.unmet') }}</span>
            </li>
        @endforeach
    </ul>

    <div @class(['lg:mt-1.5 lg:flex lg:items-center lg:gap-3' => $compact])>
        <div @class(['mt-3 h-1.5 overflow-hidden rounded-full bg-line', 'lg:mt-0 lg:h-1 lg:flex-1' => $compact]) aria-hidden="true">
            <div data-meter class="h-full w-0 rounded-full bg-primary transition-[width]"></div>
        </div>
        <p data-strength aria-live="polite" @class(['mt-1.5 font-medium text-ink', 'lg:mt-0 lg:shrink-0' => $compact])>
            {{ __('account.password_rules.strength', ['level' => __('account.password_rules.levels.empty')]) }}
        </p>
    </div>
    <p @class(['mt-1 text-muted', 'lg:mt-0.5' => $compact]) data-leaked>{{ __('account.password_rules.leaked') }}</p>
</div>
