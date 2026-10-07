<?php

namespace App\Support;

use App\Enums\FormFieldType;
use App\Models\Contracts\HasFormFields;
use App\Rules\RealPdf;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * حقول النماذج التي يحددها المسؤول (الفرص، والبرامج): قواعد التحقق من إجاباتها، وتنظيف
 * تعريفها عند الحفظ. الأنواع والأطوال تُفحص دائماً، والإلزام عند الإرسال فقط، فيُحفظ الطلب
 * مسودة ناقصاً.
 */
final class FormFieldRules
{
    public const PDF_MAX_KB = 5120;

    /**
     * @param  array<string, mixed>  $existingFiles  المرفقات المحفوظة، فلا يُطلب الملف الإلزامي مرتين
     * @return array<string, list<mixed>>
     */
    public static function for(HasFormFields $form, bool $submitting, array $existingFiles = []): array
    {
        $rules = ['answers' => ['nullable', 'array']];

        foreach ($form->fields() as $field) {
            $key = $field['key'];
            $required = $submitting && $field['required'];

            if ($field['type'] === FormFieldType::Pdf) {
                $rules['files.'.$key] = [$required && ! isset($existingFiles[$key]) ? 'required' : 'nullable', 'file', 'mimetypes:application/pdf', 'max:'.self::PDF_MAX_KB, new RealPdf];

                continue;
            }

            $rules['answers.'.$key] = [$required ? 'required' : 'nullable', ...self::typeRules($field['type'], $field['options'])];
        }

        return $rules;
    }

    /**
     * أسماء الحقول في رسائل الخطأ.
     *
     * @return array<string, string>
     */
    public static function attributes(HasFormFields $form): array
    {
        $names = [];

        foreach ($form->fields() as $field) {
            $names[($field['type'] === FormFieldType::Pdf ? 'files.' : 'answers.').$field['key']] = $field['label'];
        }

        return $names;
    }

    /**
     * تعريف الحقول كما يُحفظ: معرّفات صالحة لا تتكرر، واسم ونوع لكل حقل، وخيارات لحقل الاختيار.
     *
     * @param  array<mixed>  $fields
     * @return list<array{key: string, label: string, type: string, required: bool, options?: list<string>}>
     *
     * @throws ValidationException
     */
    public static function clean(array $fields, bool $allowFiles = true): array
    {
        $clean = [];

        foreach (array_values($fields) as $field) {
            $key = is_array($field) ? (string) ($field['key'] ?? '') : '';
            $type = is_array($field) ? FormFieldType::tryFrom((string) ($field['type'] ?? '')) : null;
            $label = is_array($field) ? trim((string) ($field['label'] ?? '')) : '';

            $invalid = preg_match('/^[a-z][a-z0-9_]{0,39}$/', $key) !== 1 || $type === null || $label === '' || isset($clean[$key])
                || ($type === FormFieldType::Pdf && ! $allowFiles);

            if ($invalid) {
                throw ValidationException::withMessages(['form_fields' => __('services.openings.invalid_fields')]);
            }

            $options = array_values(array_unique(array_filter(array_map(fn (mixed $option): string => trim((string) $option), (array) ($field['options'] ?? [])))));

            if ($type === FormFieldType::Select && $options === []) {
                throw ValidationException::withMessages(['form_fields' => __('services.openings.invalid_fields')]);
            }

            $clean[$key] = [
                'key' => $key,
                'label' => mb_substr($label, 0, 150),
                'type' => $type->value,
                'required' => (bool) ($field['required'] ?? false),
                ...($type === FormFieldType::Select ? ['options' => $options] : []),
            ];
        }

        return array_values($clean);
    }

    /**
     * @param  list<string>  $options
     * @return list<mixed>
     */
    private static function typeRules(FormFieldType $type, array $options): array
    {
        return match ($type) {
            FormFieldType::Text => ['string', 'max:255'],
            FormFieldType::Textarea => ['string', 'max:5000'],
            FormFieldType::Email => ['string', 'email', 'max:255'],
            FormFieldType::Number => ['numeric', 'between:-1000000000,1000000000'],
            FormFieldType::Date => ['date_format:Y-m-d'],
            FormFieldType::Select => ['string', Rule::in($options)],
            FormFieldType::Pdf => [],
        };
    }
}
