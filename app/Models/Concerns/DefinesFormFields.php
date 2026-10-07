<?php

namespace App\Models\Concerns;

use App\Enums\FormFieldType;

/**
 * حقول النموذج المحفوظة في form_fields بأنواعها، ويُتجاوز ما لا يُقرأ منها.
 */
trait DefinesFormFields
{
    /**
     * @return list<array{key: string, label: string, type: FormFieldType, required: bool, options: list<string>}>
     */
    public function fields(): array
    {
        return array_values(array_filter(array_map(function (mixed $field): ?array {
            $type = is_array($field) ? FormFieldType::tryFrom((string) ($field['type'] ?? '')) : null;

            if ($type === null || ! is_string($field['key'] ?? null) || ! is_string($field['label'] ?? null)) {
                return null;
            }

            return [
                'key' => $field['key'],
                'label' => $field['label'],
                'type' => $type,
                'required' => (bool) ($field['required'] ?? false),
                'options' => array_values(array_filter((array) ($field['options'] ?? []), 'is_string')),
            ];
        }, (array) ($this->form_fields ?? []))));
    }
}
