<?php

namespace App\Support;

use App\Enums\DocumentFieldType;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * حقول النماذج الإلكترونية: تنظيف تعريفها عند حفظ النموذج، وقواعد التحقق من المعبأ عليها. المرفق في
 * files والتوقيع في signatures، وما سواهما في answers.
 */
final class DocumentFields
{
    public const FILE_MAX_KB = 10240;

    public const MAX_FIELDS = 40;

    public const MAX_TABLE_COLUMNS = 6;

    public const MAX_TABLE_ROWS = 30;

    public const PARAGRAPH_MAX = 2000;

    /**
     * تعريف الحقول كما يُحفظ: معرّفات صالحة لا تتكرر، واسم ونوع لكل حقل، وخيارات لحقل الاختيار، وأعمدة
     * للجدول، ونص للفقرة الثابتة. ونصف العرض يُحفظ وحده (الكامل افتراضه)، فلا يتغير تعريف حقل قديم.
     *
     * @param  array<mixed>  $fields
     * @return list<array{key: string, label: string, type: string, required: bool, options?: list<string>, columns?: list<string>, text?: string, width?: string}>
     *
     * @throws ValidationException
     */
    public static function clean(array $fields): array
    {
        $clean = [];

        foreach (array_values($fields) as $field) {
            $key = is_array($field) ? (string) ($field['key'] ?? '') : '';
            $type = is_array($field) ? DocumentFieldType::tryFrom(self::value($field['type'] ?? '')) : null;
            $label = is_array($field) ? trim((string) ($field['label'] ?? '')) : '';
            $options = is_array($field) ? array_values(array_unique(array_filter(array_map(fn (mixed $option): string => trim((string) $option), (array) ($field['options'] ?? []))))) : [];
            $columns = is_array($field) ? array_values(array_filter(array_map(fn (mixed $column): string => mb_substr(trim((string) $column), 0, 100), (array) ($field['columns'] ?? [])))) : [];
            $text = is_array($field) ? trim((string) ($field['text'] ?? '')) : '';
            $width = (is_array($field) ? (string) ($field['width'] ?? '') : '') ?: 'full';

            if (preg_match('/^[a-z][a-z0-9_]{0,39}$/', $key) !== 1 || $type === null || $label === '' || isset($clean[$key])
                || ($type === DocumentFieldType::Select && $options === [])
                || ($type === DocumentFieldType::Table && ($columns === [] || count($columns) > self::MAX_TABLE_COLUMNS))
                || ($type === DocumentFieldType::Paragraph && ($text === '' || mb_strlen($text) > self::PARAGRAPH_MAX))
                || ! in_array($width, ['full', 'half'], true)) {
                throw ValidationException::withMessages(['fields' => __('documents.validation.invalid_fields')]);
            }

            // عناصر التخطيط والجدول بعرض النموذج كله
            $half = $width === 'half' && ! $type->isLayout() && $type !== DocumentFieldType::Table;

            $clean[$key] = [
                'key' => $key,
                'label' => mb_substr($label, 0, 150),
                'type' => $type->value,
                'required' => ! $type->isLayout() && (bool) ($field['required'] ?? false),
                ...($type === DocumentFieldType::Select ? ['options' => $options] : []),
                ...($type === DocumentFieldType::Table ? ['columns' => $columns] : []),
                ...($type === DocumentFieldType::Paragraph ? ['text' => $text] : []),
                ...($half ? ['width' => 'half'] : []),
            ];
        }

        if ($clean === [] || count($clean) > self::MAX_FIELDS) {
            throw ValidationException::withMessages(['fields' => __('documents.validation.invalid_fields')]);
        }

        return array_values($clean);
    }

    /**
     * قواعد المعبأ على حقول إصداره.
     *
     * @param  list<array{key: string, label: string, type: DocumentFieldType, required: bool, options: list<string>, columns: list<string>, text: string, half: bool}>  $fields
     * @return array<string, list<mixed>>
     */
    public static function rules(array $fields): array
    {
        $rules = [
            'answers' => ['nullable', 'array'],
            'files' => ['nullable', 'array'],
            'signatures' => ['nullable', 'array'],
        ];

        foreach ($fields as $field) {
            $key = $field['key'];
            $required = $field['required'] ? 'required' : 'nullable';

            if ($field['type']->isLayout()) {
                continue;
            }

            if ($field['type'] === DocumentFieldType::Table) {
                $rules['answers.'.$key] = [$required, 'array', 'list', 'max:'.self::MAX_TABLE_ROWS];
                $rules['answers.'.$key.'.*'] = ['array:'.implode(',', self::columnKeys($field))];
                $rules['answers.'.$key.'.*.*'] = ['nullable', 'string', 'max:255'];
            } elseif ($field['type'] === DocumentFieldType::File) {
                $rules['files.'.$key] = [$required, 'array'];
                $rules['files.'.$key.'.path'] = ['required_with:files.'.$key, 'string', 'max:255'];
                $rules['files.'.$key.'.name'] = ['required_with:files.'.$key, 'string', 'max:200'];
            } elseif ($field['type'] === DocumentFieldType::Signature) {
                $rules['signatures.'.$key] = [$required, 'string', 'starts_with:data:image/png;base64,', 'max:'.(SignatureImage::MAX_BYTES * 2)];
            } else {
                $rules['answers.'.$key] = [$required, ...self::typeRules($field)];
            }
        }

        return $rules;
    }

    /**
     * أسماء الحقول في رسائل الخطأ.
     *
     * @param  list<array{key: string, label: string, type: DocumentFieldType, required: bool, options: list<string>, columns: list<string>, text: string, half: bool}>  $fields
     * @return array<string, string>
     */
    public static function attributes(array $fields): array
    {
        $names = [];

        foreach ($fields as $field) {
            $names[self::group($field['type']).'.'.$field['key']] = $field['label'];
        }

        return $names;
    }

    /**
     * مفاتيح خلايا صف الجدول بترتيب أعمدته: c0، وc1، وهكذا.
     *
     * @param  array{columns: list<string>}  $field
     * @return list<string>
     */
    public static function columnKeys(array $field): array
    {
        return array_map(fn (int $index): string => 'c'.$index, array_keys($field['columns']));
    }

    public static function group(DocumentFieldType $type): string
    {
        return match ($type) {
            DocumentFieldType::File => 'files',
            DocumentFieldType::Signature => 'signatures',
            default => 'answers',
        };
    }

    /**
     * @param  array{type: DocumentFieldType, options: list<string>}  $field
     * @return list<mixed>
     */
    private static function typeRules(array $field): array
    {
        return match ($field['type']) {
            DocumentFieldType::LongText => ['string', 'max:5000'],
            DocumentFieldType::Number => ['numeric', 'between:-1000000000,1000000000'],
            DocumentFieldType::Date => ['date_format:Y-m-d'],
            DocumentFieldType::Select => ['string', Rule::in($field['options'])],
            default => ['string', 'max:255'],
        };
    }

    /**
     * من اللوحة يصل التعداد نفسه، ومن غيرها قيمته.
     */
    private static function value(mixed $type): string
    {
        return $type instanceof DocumentFieldType ? $type->value : (string) $type;
    }
}
