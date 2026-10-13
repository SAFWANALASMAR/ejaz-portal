<?php

namespace App\Http\Requests;

use App\Rules\PlainText;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * البحث والتصفية في الأخبار. والمدخل الخاطئ يعيد إلى القائمة بلا تصفية.
 */
class NewsIndexRequest extends FormRequest
{
    protected $redirectRoute = 'media.news.index';

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
            'category' => ['nullable', 'string', 'max:60', new PlainText],
            'page' => ListPage::RULES,
        ];
    }
}
