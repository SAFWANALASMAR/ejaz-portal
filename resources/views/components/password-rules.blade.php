{{--
    شروط كلمة المرور تحت حقلها، تُعلَّم عند تحققها أثناء الكتابة، ومؤشر قوتها نصاً يُعلَن لقارئ الشاشة (password-rules.js).
    والقواعد نفسها في الخادم (PasswordPolicy)، فهذا عون للكتابة لا تحقق. وفحص البيانات الشخصية من حقول الاسم والبريد
    والجوال في الصفحة نفسها إن وُجدت (التسجيل و«بياناتي»)، وما لا يُعرف في المتصفح يفحصه الخادم.
--}}
@props(['for' => 'field-password'])

@php
    $rules = ['length', 'letter', 'mixed', 'number', 'symbol', 'personal_item'];
@endphp

<div data-password-rules data-for="{{ $for }}"
    data-met="{{ __('account.password_rules.met') }}" data-unmet="{{ __('account.password_rules.unmet') }}"
    data-strength-label="{{ __('account.password_rules.strength') }}"
    @foreach (['empty', 'weak', 'fair', 'strong'] as $level) data-level-{{ $level }}="{{ __('account.password_rules.levels.'.$level) }}" @endforeach
    {{ $attributes->merge(['class' => 'rounded-xl border border-line bg-surface p-4 text-sm']) }}>
    <p class="font-semibold text-ink" id="{{ $for }}-rules-heading">{{ __('account.password_rules.heading') }}</p>

    <ul class="mt-2 space-y-1.5" aria-labelledby="{{ $for }}-rules-heading">
        @foreach ($rules as $rule)
            <li data-rule="{{ $rule }}" data-state="unmet" class="flex items-start gap-2 text-muted data-[state=met]:text-primary">
                <span aria-hidden="true" class="mt-0.5 inline-flex size-4 shrink-0 items-center justify-center rounded-full border border-current text-[10px] leading-none" data-mark>•</span>
                <span>{{ __('account.password_rules.'.$rule) }}</span>
                <span class="sr-only" data-state-text>{{ __('account.password_rules.unmet') }}</span>
            </li>
        @endforeach
    </ul>

    <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-line" aria-hidden="true">
        <div data-meter class="h-full w-0 rounded-full bg-primary transition-[width]"></div>
    </div>
    <p data-strength aria-live="polite" class="mt-1.5 font-medium text-ink">
        {{ __('account.password_rules.strength', ['level' => __('account.password_rules.levels.empty')]) }}
    </p>
    <p class="mt-1 text-muted">{{ __('account.password_rules.leaked') }}</p>
</div>
