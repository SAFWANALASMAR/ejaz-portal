<?php

namespace App\Http\Requests\Assembly;

use App\Enums\AssemblyFileCategory;
use App\Http\Requests\ListPage;
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
            'page' => ListPage::RULES,
        ];
    }
}
