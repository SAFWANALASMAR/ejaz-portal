{{--
    حقول نموذج الفرصة التي حددها المسؤول، بقيم الطلب المحفوظة إن وُجد. ملف PDF يُرفع من جديد
    ليحل محل الحالي.
--}}
@php
    use App\Enums\FormFieldType;
    use App\Support\FormFieldRules;

    $answers = $serviceRequest?->answers ?? [];
    $files = $serviceRequest?->files ?? [];
@endphp

@forelse ($opening->fields() as $field)
    @php($name = 'answers['.$field['key'].']')
    @switch($field['type'])
        @case(FormFieldType::Textarea)
            <x-field :name="$name" type="textarea" :label="$field['label']" :value="$answers[$field['key']] ?? null" :required="$field['required']" maxlength="5000" class="md:col-span-2" />
            @break
        @case(FormFieldType::Select)
            <x-field :name="$name" type="select" :label="$field['label']" :required="$field['required']">
                <option value="">{{ __('services.form.choose') }}</option>
                @foreach ($field['options'] as $option)
                    <option value="{{ $option }}" @selected(old('answers.'.$field['key'], $answers[$field['key']] ?? null) === $option)>{{ $option }}</option>
                @endforeach
            </x-field>
            @break
        @case(FormFieldType::Pdf)
            @php($id = 'field-files-'.$field['key'])
            @php($error = $errors->first('files.'.$field['key']))
            <div class="space-y-1.5 md:col-span-2">
                <label for="{{ $id }}" class="block text-sm font-semibold text-ink">
                    {{ $field['label'] }}
                    @if ($field['required'])
                        <span class="text-red-700" aria-hidden="true">*</span>
                    @endif
                </label>
                <input id="{{ $id }}" type="file" name="files[{{ $field['key'] }}]" accept="application/pdf" aria-describedby="{{ $id }}-hint"
                    @if ($error) aria-invalid="true" @endif
                    class="block w-full rounded-xl border border-line bg-white px-4 py-2.5 text-ink file:me-4 file:rounded-full file:border-0 file:bg-primary-soft file:px-4 file:py-1.5 file:font-semibold file:text-primary">
                <p id="{{ $id }}-hint" class="text-sm text-muted">
                    {{ __('services.form.pdf_hint', ['size' => FormFieldRules::PDF_MAX_KB / 1024]) }}
                    @isset($files[$field['key']])
                        {{ __('services.form.current_file', ['name' => $files[$field['key']]['name']]) }}
                    @endisset
                </p>
                @if ($error)
                    <p class="text-sm font-medium text-red-700">{{ $error }}</p>
                @endif
            </div>
            @break
        @default
            <x-field :name="$name" :type="$field['type'] === FormFieldType::Text ? 'text' : $field['type']->value" :label="$field['label']"
                :value="$answers[$field['key']] ?? null" :required="$field['required']"
                :dir="in_array($field['type'], [FormFieldType::Email, FormFieldType::Number], true) ? 'ltr' : null" />
    @endswitch
@empty
    <p class="text-muted md:col-span-2">{{ __('services.form.no_fields') }}</p>
@endforelse
