{{-- حقل نموذج موحد: تسمية، ومدخل، وتلميح، ورسالة خطأ مرتبطة به للقارئات الصوتية --}}
@props([
    'name',
    'label',
    'type' => 'text',
    'value' => null,
    'hint' => null,
    'required' => false,
    'dir' => null,
    'bag' => 'default',
])

@php
    $id = $attributes->get('id', 'field-'.str_replace(['[', ']', '.'], '-', $name));
    $errorKey = str_replace(['[', ']'], ['.', ''], $name);
    $error = $errors->getBag($bag)->first($errorKey);
    $describedBy = trim(($hint ? $id.'-hint ' : '').($error ? $id.'-error' : ''));
    $inputClasses = 'block w-full rounded-lg border bg-white px-4 py-2.5 text-ink shadow-xs placeholder:text-muted/70 focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none '
        .($error ? 'border-red-600' : 'border-line');
@endphp

<div {{ $attributes->only('class')->merge(['class' => 'space-y-1.5']) }}>
    <label for="{{ $id }}" class="block text-sm font-semibold text-ink">
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
        <input
            id="{{ $id }}"
            name="{{ $name }}"
            type="{{ $type }}"
            @if ($type !== 'password') value="{{ old($errorKey, $value) }}" @endif
            @if ($dir) dir="{{ $dir }}" @endif
            @if ($required) required @endif
            @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
            @if ($error) aria-invalid="true" @endif
            {{ $attributes->except(['class', 'id'])->merge(['class' => $inputClasses]) }}
        >
    @endif

    @if ($hint)
        <p id="{{ $id }}-hint" class="text-sm text-muted">{{ $hint }}</p>
    @endif

    @if ($error)
        <p id="{{ $id }}-error" class="text-sm font-medium text-red-700">{{ $error }}</p>
    @endif
</div>
