<?php

namespace App\Http\Requests;

use App\Enums\StoreItemCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * التصفية في «المتجر والتبرعات» بالتصنيف من القائمة المسموحة. والمدخل الخاطئ يعيد إلى الصفحة بلا تصفية.
 */
class StoreIndexRequest extends FormRequest
{
    protected $redirectRoute = 'store.index';

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
            'category' => ['nullable', 'string', Rule::enum(StoreItemCategory::class)],
        ];
    }
}
