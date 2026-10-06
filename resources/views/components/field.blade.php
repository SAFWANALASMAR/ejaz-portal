{{--
    حقل نموذج موحد: تسمية (ظاهرة أو للقارئات الصوتية فقط)، وأيقونة داخل الحقل في بدايته،
    وزر إظهار كلمة المرور، وتلميح، ورسالة خطأ مرتبطة بالحقل.
--}}
@props([
    'name',
    'label',
    'type' => 'text',
    'value' => null,
    'hint' => null,
    'required' => false,
    'dir' => null,
    'bag' => 'default',
    'icon' => null,
    'hideLabel' => false,
])

@php
    $id = $attributes->get('id', 'field-'.str_replace(['[', ']', '.'], '-', $name));
    $errorKey = str_replace(['[', ']'], ['.', ''], $name);
    $error = $errors->getBag($bag)->first($errorKey);
    $describedBy = trim(($hint ? $id.'-hint ' : '').($error ? $id.'-error' : ''));
    $isPassword = $type === 'password';
    $inputClasses = 'block w-full rounded-xl border bg-white py-3 text-ink shadow-xs placeholder:text-muted/70 focus:border-primary focus:ring-2 focus:ring-primary/15 focus:outline-none '
        .($icon ? 'ps-11 ' : 'ps-4 ')
        .($isPassword ? 'pe-11 ' : 'pe-4 ')
        .($error ? 'border-red-600' : 'border-line');
@endphp

<div {{ $attributes->only('class')->merge(['class' => 'space-y-1.5']) }}>
    <label for="{{ $id }}" @class(['block text-sm font-semibold text-ink', 'sr-only' => $hideLabel])>
        {{ $label }}
        @if ($required)
            <span class="text-red-700" aria-hidden="true">*</span>
        @endif
    </label>

    @if ($type === 'textarea')
        <textarea
            id="{{ $id }}"
            name="{{ $name }}"
            rows="{{ $attributes->get('rows', 5) }}"
            @if ($required) required @endif
            @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
            @if ($error) aria-invalid="true" @endif
            {{ $attributes->except(['class', 'id', 'rows'])->merge(['class' => $inputClasses]) }}
        >{{ old($errorKey, $value) }}</textarea>
    @elseif ($type === 'select')
        <select
            id="{{ $id }}"
            name="{{ $name }}"
            @if ($required) required @endif
            @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
            @if ($error) aria-invalid="true" @endif
            {{ $attributes->except(['class', 'id'])->merge(['class' => $inputClasses]) }}
        >
            {{ $slot }}
        </select>
    @else
        <div class="relative">
            @if ($icon)
                <span class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-4 text-muted" aria-hidden="true">
                    {{ svg('heroicon-o-'.$icon, 'size-5') }}
                </span>
            @endif

            <input
                id="{{ $id }}"
                name="{{ $name }}"
                type="{{ $type }}"
                @if (! $isPassword) value="{{ old($errorKey, $value) }}" @endif
                @if ($dir) dir="{{ $dir }}" @endif
                @if ($required) required @endif
                @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
                @if ($error) aria-invalid="true" @endif
                {{ $attributes->except(['class', 'id'])->merge(['class' => $inputClasses]) }}
            >

            @if ($isPassword)
                <button type="button" data-password-toggle="{{ $id }}" aria-pressed="false" aria-label="{{ __('account.show_password') }}"
                    class="absolute inset-y-0 end-0 flex items-center pe-4 text-muted hover:text-primary">
                    <span data-icon-show>{{ svg('heroicon-o-eye', 'size-5') }}</span>
                    <span data-icon-hide hidden>{{ svg('heroicon-o-eye-slash', 'size-5') }}</span>
                </button>
            @endif
        </div>
    @endif

    @if ($hint)
        <p id="{{ $id }}-hint" class="text-sm text-muted">{{ $hint }}</p>
    @endif

    @if ($error)
        <p id="{{ $id }}-error" class="text-sm font-medium text-red-700">{{ $error }}</p>
    @endif
</div>
