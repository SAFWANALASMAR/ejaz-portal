<?php

namespace App\Http\Requests;

use App\Rules\PlainText;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * البحث والتصفية في قسم من المكتبة. والمدخل الخاطئ يعيد إلى القسم بلا تصفية.
 */
class LibraryIndexRequest extends FormRequest
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
            'q' => ['nullable', 'string', 'max:100', new PlainText],
            'tag' => ['nullable', 'string', 'max:40', new PlainText],
            'page' => ListPage::RULES,
        ];
    }

    protected function getRedirectUrl(): string
    {
        return route('library.section', (string) $this->route('section'));
    }
}
