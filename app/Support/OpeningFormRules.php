<?php

namespace App\Support;

use App\Enums\FormFieldType;
use App\Models\Opening;
use App\Models\ServiceRequest;
use App\Rules\RealPdf;
use Illuminate\Validation\Rule;

/**
 * قواعد التحقق من نموذج فرصة بحقوله التي حددها المسؤول. الأنواع والأطوال تُفحص دائماً،
 * والإلزام عند الإرسال فقط، فيُحفظ الطلب مسودة ناقصاً.
 */
final class OpeningFormRules
{
    public const PDF_MAX_KB = 5120;

    /**
     * @return array<string, list<mixed>>
     */
    public static function for(Opening $opening, bool $submitting, ?ServiceRequest $existing = null): array
    {
        $rules = ['answers' => ['nullable', 'array']];

        foreach ($opening->fields() as $field) {
            $key = $field['key'];
            $required = $submitting && $field['required'];

            if ($field['type'] === FormFieldType::Pdf) {
                $hasFile = isset($existing?->files[$key]);
                $rules['files.'.$key] = [$required && ! $hasFile ? 'required' : 'nullable', 'file', 'mimetypes:application/pdf', 'max:'.self::PDF_MAX_KB, new RealPdf];

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
    public static function attributes(Opening $opening): array
    {
        $names = [];

        foreach ($opening->fields() as $field) {
            $names[($field['type'] === FormFieldType::Pdf ? 'files.' : 'answers.').$field['key']] = $field['label'];
        }

        return $names;
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
