<?php

namespace App\Http\Requests;

use App\Enums\DocumentCategory;
use App\Rules\PlainText;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * البحث والتصفية في صفحة الحوكمة. والمدخل الخاطئ يعيد إلى الصفحة بلا تصفية.
 */
class GovernanceIndexRequest extends FormRequest
{
    protected $redirectRoute = 'governance.index';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'category' => ['nullable', Rule::enum(DocumentCategory::class)],
            'year' => ['nullable', 'integer', 'between:1990,'.(now()->year + 1)],
            'q' => ['nullable', 'string', 'max:100', new PlainText],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ];
    }
}
