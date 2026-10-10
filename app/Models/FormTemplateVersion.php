<?php

namespace App\Models;

use App\Enums\DocumentFieldType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * إصدار من حقول النموذج الإلكتروني. لا يُعدَّل بعد إنشائه، فالمعبأ عليه يبقى بحقوله.
 *
 * @property list<array{key: string, label: string, type: string, required: bool, options?: list<string>, columns?: list<string>, text?: string, width?: string}> $fields
 */
class FormTemplateVersion extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fields' => 'array',
            'version' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<FormTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(FormTemplate::class, 'form_template_id')->withTrashed();
    }

    /**
     * الحقول بأنواعها.
     *
     * @return list<array{key: string, label: string, type: DocumentFieldType, required: bool, options: list<string>, columns: list<string>, text: string, half: bool}>
     */
    public function typedFields(): array
    {
        return array_map(fn (array $field): array => [
            'key' => $field['key'],
            'label' => $field['label'],
            'type' => DocumentFieldType::from($field['type']),
            'required' => (bool) $field['required'],
            'options' => $field['options'] ?? [],
            'columns' => $field['columns'] ?? [],
            'text' => $field['text'] ?? '',
            'half' => ($field['width'] ?? 'full') === 'half',
        ], $this->fields);
    }
}
