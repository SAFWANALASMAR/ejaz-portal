<?php

namespace App\Http\Requests\Competitions;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * حفظ مسودة المشاركة أو إرسالها: شكل المدخلات وحدودها. والفحص بأسئلة المسابقة نفسها (الخيار من
 * خيارات سؤاله مثلاً) في SaveSubmissionDraft، والصلاحية هناك أيضاً.
 */
class SaveSubmissionRequest extends FormRequest
{
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
            'category' => ['nullable', 'string', 'max:60'],
            'region' => ['nullable', 'string', 'max:30'],
            'answers' => ['nullable', 'array', 'max:200'],
            'answers.*' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function entry(): array
    {
        return $this->safe()->only(['category', 'region', 'answers']);
    }
}
