<?php

namespace App\Models\Contracts;

use App\Enums\FormFieldType;

/**
 * سجل له نموذج بحقول يحددها المسؤول (الفرص، والبرامج)، محفوظة في form_fields.
 */
interface HasFormFields
{
    /**
     * @return list<array{key: string, label: string, type: FormFieldType, required: bool, options: list<string>}>
     */
    public function fields(): array;
}
