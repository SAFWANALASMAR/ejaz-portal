<?php

namespace App\Http\Requests\Assembly;

use App\Enums\AssemblyFileCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * تصفية مكتبة ملفات الأعضاء بالتصنيف. والمدخل الخاطئ يعيد إلى المكتبة بلا تصفية. والوصول في المسار
 * (can:accessAssemblyArea).
 */
class AssemblyFilesRequest extends FormRequest
{
    protected $redirectRoute = 'assembly.files.index';

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
            'category' => ['nullable', Rule::enum(AssemblyFileCategory::class)],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ];
    }
}
