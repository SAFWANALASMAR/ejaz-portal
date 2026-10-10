<?php

namespace App\Http\Requests\Competitions;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * بدء رفع مقطع: الحجم والنوع والمدة كما قرأها المتصفح من الملف. وتُفحص مقابل حدود السؤال في
 * StartVideoUpload، ثم يُعاد فحص المقطع نفسه بعد استلامه (ProcessSubmissionVideo).
 */
class StartVideoUploadRequest extends FormRequest
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
            'size' => ['required', 'integer', 'min:1'],
            'type' => ['required', 'string', 'max:100'],
            'duration' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
